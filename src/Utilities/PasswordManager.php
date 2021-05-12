<?php


namespace Phabalicious\Utilities;

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\UnknownSecretException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Questions\Question;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Yaml\Yaml;

class PasswordManager implements PasswordManagerInterface
{

    private $context;

    private $passwords;

    private $questionFactory = null;

    public function __construct()
    {
        $this->readPasswords();
    }

    public function getPasswordFor(string $key)
    {
        if (!empty($this->passwords[$key])) {
            return $this->passwords[$key];
        }

        $pw = $this->context->askQuestion(sprintf('Please provide a secret for `%s`: ', $key));
        $this->passwords[$key] = $pw;
        return $pw;
    }

    public function getKeyFromLogin($host, $port, $user): string
    {
        return sprintf('%s@%s:%s', $user, $host, $port);
    }

    public function getQuestionFactory(): QuestionFactory
    {
        if (!$this->questionFactory) {
            $this->questionFactory = new QuestionFactory();
        }
        return $this->questionFactory;
    }

    private function readPasswords()
    {
        $file = getenv("HOME"). '/.phabalicious-credentials';
        if (!file_exists($file)) {
            return;
        }

        $data = Yaml::parseFile($file);

        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Could not parse %s', $file));
        }

        $this->passwords = $data;
    }

    /**
     * @return TaskContextInterface
     */
    public function getContext(): TaskContextInterface
    {
        return $this->context;
    }

    /**
     * @param TaskContextInterface $context
     *
     * @return PasswordManager
     */
    public function setContext(TaskContextInterface $context): PasswordManagerInterface
    {
        $this->context = $context;
        return $this;
    }

    public function resolveSecrets($data)
    {
        $was_array = is_array($data);
        if (!$was_array) {
            $data = [$data];
        }
        $replacements = [];
        $this->resolveSecretsImpl($data, $replacements);
        $data = Utilities::expandStrings($data, $replacements);

        return $was_array ? $data : $data[0];
    }

    private function resolveSecretsImpl(array $data, array &$replacements)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->resolveSecretsImpl($value, $replacements);
            } elseif (is_string($value) && ($secret_keys = $this->containsSecrets($value))) {
                foreach ($secret_keys as $secret_key) {
                    $replacements['%secret.' . $secret_key . '%'] = $this->getSecret($secret_key);
                }
            }
        }
    }

    private function containsSecrets(string $string)
    {
        $matches = [];
        if (preg_match_all("/%secret\.(.*?)%/", $string, $matches)) {
            return $matches[1];
        }
        return false;
    }

    private function getSecret($secret)
    {
        $configuration_service = $this->getContext()->getConfigurationService();
        $secrets = $configuration_service
            ->getSetting('secrets', []);
        if (!isset($secrets[$secret])) {
            throw new UnknownSecretException("Could not find secret `$secret` in config!");
        }

        if (isset($this->passwords[$secret])) {
            return $this->passwords[$secret];
        }

        $secret_data = $secrets[$secret];

        $env_name = !empty($secret_data['env'])
            ? $secret_data['env']
            : str_replace('.', '_', Utilities::toUpperSnakeCase($secret));
        if ($value = getenv($env_name)) {
            return $value;
        }

        static $envvars = [];
        $env_file = $configuration_service->getFabfilePath() . '/.env';
        if (empty($envvars) && file_exists($env_file)) {
            $dotenv = new \Symfony\Component\Dotenv\Dotenv();
            $contents = file_get_contents($env_file);
            $envvars = $dotenv->parse($contents);
        }
        if (isset($envvars[$env_name])) {
            return $envvars[$env_name];
        }

        $args = $this->getContext()->getInput()->getOption('secret');
        foreach ($args as $p) {
            [$key, $value] = explode('=', $p);
            if ($key == $secret) {
                return $value;
            }
        }

        // Still no match, ask for it!

        if (!$this->context) {
            throw new \RuntimeException("Cant resolve secrets as no valid context is available!");
        }

        // Check onepassword connect ...
        if (!empty($secret_data['onePasswordVaultId']) && !empty($secret_data['onePasswordId'])) {
            if ($pw = $this->getSecretFrom1PasswordConnect(
                $secret_data['onePasswordVaultId'],
                $secret_data['onePasswordId'],
                $secret
            )) {
                return $pw;
            } else {
                $configuration_service->getLogger()->warning(
                    'No configuration for onePassword-connect found, skipping ...'
                );
            }
        }

        // Check onepassword cli ...
        if (isset($secret_data['onePasswordId'])) {
            $pw = $this->getSecretFrom1PasswordCli($secret_data['onePasswordId']);
            if ($pw) {
                return $pw;
            }
        }

        $pw = $this->getQuestionFactory()->askAndValidate($this->getContext()->io(), $secret_data, null);

        if (is_null($pw)) {
            throw new \RuntimeException(sprintf(
                "Could not determine value for secret `%s`!\n\n" .
                "Use `setenv %s=<value>` or \nadd `--secret %s=<value>` to your command",
                $secret,
                $env_name,
                $secret
            ));
        }

        $this->passwords[$secret] = $pw;
        return $pw;
    }

    private function getSecretFrom1PasswordCli($item_id)
    {
        $op_file_path = getenv('PHAB_OP_FILE_PATH') ?: '/usr/local/bin/op';
        if (!$op_file_path || !file_exists($op_file_path)) {
            return false;
        }

        $output = [];
        $result_code = 0;
        $result = exec(sprintf("%s get item %s", $op_file_path, $item_id), $output, $result_code);
        if ($result_code == 0) {
            $payload = implode("\n", $output);
            return $this->extractSecretFrom1PasswordPayload($payload, true);
        } else {
            throw new \RuntimeException("1Password returned an error, are you logged in?");
        }
    }

    private function getSecretFrom1PasswordConnect($vault_id, $item_id, $secret_name)
    {

        $configuration_service = $this->getContext()->getConfigurationService();
        $onepassword_connect = $configuration_service->getSetting('onePassword', []);
        if ($token = getenv("PHAB_OP_JWT_TOKEN")) {
            $onepassword_connect['token'] = $token;
        }
        if (is_array($onepassword_connect)) {
            $errors = new ValidationErrorBag();
            $validation_service = new ValidationService($onepassword_connect, $errors, 'onePassword');
            $validation_service->hasKeys([
                'token' => 'The access token to authenticate against onePassword connect',
                'endpoint' => 'The onePassword api endpoint to connect to'
            ]);

            if ($errors->hasErrors()) {
                throw new ValidationFailedException($errors);
            }

            try {
                $url = $onepassword_connect['endpoint'] . "/v1/vaults/$vault_id/items/$item_id";
                $configuration_service->getLogger()->info(
                    sprintf("Querying %s for secret `%s` ...", $url, $secret_name)
                );

                $client = new Client();
                $response = $client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $onepassword_connect['token']
                    ]
                ]);
                return $this->extractSecretFrom1PasswordPayload((string) $response->getBody(), false);
            } catch (\Exception $exception) {
                throw new \RuntimeException(
                    sprintf("Could not get secret `%s` from 1password-connect", $secret_name),
                    0,
                    $exception
                );
            }
        }

        return false;
    }

    private function extractSecretFrom1PasswordPayload($payload, $cli)
    {
        $json = json_decode($payload);
        if ($cli) {
            $json = $json->details;
        }
        if (!empty($json->password)) {
            return $json->password;
        }
        if (!empty($json->fields)) {
            $fields = $json->fields;
            foreach ($fields as $field) {
                if (!empty($field->designation) && $field->designation == 'password') {
                    return $field->value;
                }
                if (!empty($field->purpose) && $field->purpose == 'PASSWORD') {
                    return $field->value;
                }
            }
        }
        $this->getContext()->getConfigurationService()->getLogger()->warning(
            "Could not get password from 1password!\n" . $payload
        );
        return false;
    }
}
