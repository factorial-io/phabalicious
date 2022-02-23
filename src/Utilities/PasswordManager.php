<?php


namespace Phabalicious\Utilities;

use Defuse\Crypto\Crypto;
use GuzzleHttp\Client;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\UnknownSecretException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;

class PasswordManager implements PasswordManagerInterface
{

    /** @var TaskContextInterface  */
    private $context;

    private $passwords = [];

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

    public function getSecret($secret)
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

        $this->passwords[$secret] = $this->getSecretImpl($configuration_service, $secret, $secret_data);
        return $this->passwords[$secret];
    }

    private function getSecretImpl(ConfigurationService $configuration_service, $secret, $secret_data)
    {

        $env_name = !empty($secret_data['env'])
            ? $secret_data['env']
            : str_replace('.', '_', Utilities::toUpperSnakeCase($secret));

        $configuration_service->getLogger()->debug(sprintf(
            "Trying to get secret `%s` from env-var `%s` ...",
            $secret,
            $env_name
        ));

        if ($value = getenv($env_name)) {
            return $value;
        }

        static $envvars = [];
        $env_file = $configuration_service->getFabfilePath() . '/.env';
        if (empty($envvars) && file_exists($env_file)) {
            $dotenv = new Dotenv();
            $contents = file_get_contents($env_file);
            $envvars = $dotenv->parse($contents);
        }
        if (isset($envvars[$env_name])) {
            return $envvars[$env_name];
        }

        $configuration_service->getLogger()->debug(sprintf(
            "Trying to get secret `%s` from command-line-argument `--secret %s=<VALUE>` ...",
            $secret,
            $secret
        ));

        $args = $this->getContext()->getInput()->getOption('secret');
        if (!is_array($args)) {
            $args = [ $args ];
        }
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

        try {
            // Check onepassword connect ...
            if (!empty($secret_data['onePasswordVaultId']) && !empty($secret_data['onePasswordId'])) {
                $configuration_service->getLogger()->debug(sprintf(
                    "Trying to get secret `%s` from 1password.connect",
                    $secret
                ));

                if ($pw = $this->getSecretFrom1PasswordConnect(
                    $secret_data['onePasswordVaultId'],
                    $secret_data['onePasswordId'],
                    $secret_data['tokenId'] ?? 'default',
                    $secret
                )) {
                    return $pw;
                } else {
                    $configuration_service->getLogger()->warning(
                        'No configuration for onePassword-connect found, skipping ...'
                    );
                }
            }
        } catch (\Exception $e) {
            $configuration_service->getLogger()->error($e->getMessage());
            // Give the user the chance to input the secret.
        }

        try {
            // Check onepassword cli ...
            if (isset($secret_data['onePasswordId'])) {
                $configuration_service->getLogger()->debug(sprintf(
                    "Trying to get secret `%s` from 1password cli",
                    $secret
                ));
                $pw = $this->getSecretFrom1PasswordCli($secret_data['onePasswordId']);
                if ($pw) {
                    return $pw;
                }
            }
        } catch (\Exception $e) {
            $configuration_service->getLogger()->error($e->getMessage());
            // Give the user the chance to input the secret.
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

        return $pw;
    }

    private function exec1PasswordCli($cmd)
    {
        $op_file_path = getenv('PHAB_OP_FILE_PATH') ?: '/usr/local/bin/op';
        if (!$op_file_path || !file_exists($op_file_path)) {
            return new CommandResult(1, ['COuld not find 1password binary.']);
        }

        $output = [];
        $result_code = 0;
        $cmd = sprintf("%s %s", $op_file_path, $cmd);
        $this->context->getConfigurationService()->getLogger()->info(sprintf("Running 1password cli with `%s`", $cmd));
        $result = exec($cmd, $output, $result_code);
        return new CommandResult($result_code, $output);
    }

    private function getSecretFrom1PasswordCli($item_id)
    {
        $result = $this->exec1PasswordCli(sprintf("get item %s", $item_id));

        if ($result && $result->succeeded()) {
            $payload = implode("\n", $result->getOutput());
            return $this->extractSecretFrom1PasswordPayload($payload, true);
        }

        $result->throwException("1Password returned an error, are you logged in?");
    }

    private function getFileFrom1PasswordCli($item_id, $target_file_dir)
    {
        return $this->exec1PasswordCli(sprintf('get document %s > %s', $item_id, $target_file_dir));
    }

    private function get1PasswordConnectResponse($token_id, $url)
    {
        $configuration_service = $this->getContext()->getConfigurationService();
        $onepassword_connect = $configuration_service->getSetting("onePassword.$token_id", []);
        if ($token = getenv("PHAB_OP_JWT_TOKEN__" . Utilities::toUpperSnakeCase($token_id))) {
            $onepassword_connect['token'] = $token;
        }
        if (is_array($onepassword_connect)) {
            $errors = new ValidationErrorBag();
            $validation_service = new ValidationService($onepassword_connect, $errors, "onePassword.$token_id");
            $validation_service->hasKeys([
                'token' => 'The access token to authenticate against onePassword connect',
                'endpoint' => 'The onePassword api endpoint to connect to'
            ]);

            if ($errors->hasErrors()) {
                throw new ValidationFailedException($errors);
            }

            $url = $onepassword_connect['endpoint'] . $url;
            $configuration_service->getLogger()->debug(
                sprintf("Querying %s ...", $url)
            );

            $client = new Client();
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $onepassword_connect['token']
                ]
            ]);

            return $response;
        }
        return false;
    }

    private function getSecretFrom1PasswordConnect($vault_id, $item_id, $token_id, $secret_name)
    {
        try {
            $response = $this->get1PasswordConnectResponse($token_id, "/v1/vaults/$vault_id/items/$item_id");
            if ($response) {
                return $this->extractSecretFrom1PasswordPayload((string) $response->getBody(), false);
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(
                sprintf("Could not get secret `%s` from 1password-connect: %s", $secret_name, $exception->getMessage()),
                0,
                $exception
            );
        }

        return false;
    }

    private function extractFieldsHelper($fields)
    {

        foreach ($fields as $field) {
            if (!empty($field->designation) && $field->designation === 'password') {
                return $field->value;
            }
            if (!empty($field->purpose) && $field->purpose === 'PASSWORD') {
                return $field->value;
            }
            if (!empty($field->id) && $field->id == 'password') {
                return $field->value;
            }
            // Support for field in sections.
            if (!empty($field->n) && $field->n === 'password') {
                return $field->v;
            }
        }
        return false;
    }

    private function extractSecretFrom1PasswordPayload($payload, $cli)
    {
        $json = json_decode($payload);
        if ($json) {
            if ($cli) {
                $json = $json->details;
            }
            if (!empty($json->password)) {
                return $json->password;
            }
            if (!empty($json->sections)) {
                foreach ($json->sections as $section) {
                    if (isset($secton->fields) && $result = $this->extractFieldsHelper($section->fields)) {
                        return $result;
                    }
                }
            }
            if (!empty($json->fields)) {
                return $this->extractFieldsHelper($json->fields);
            }
        }

        $this->getContext()->getConfigurationService()->getLogger()->warning(
            "Could not get password from 1password!\n" . $payload
        );
        return false;
    }

    public function encrypt($data, $secret_name)
    {
        $secret = $this->getSecret($secret_name, $binary = false);
        return Crypto::encryptWithPassword($data, $secret, $binary);
    }

    public function decrypt($data, $secret_name)
    {
        $secret = $this->getSecret($secret_name);
        return Crypto::decryptWithPassword($data, $secret);
    }

    public function setSecret($secret_name, $value)
    {
        $this->passwords[$secret_name] = $value;
    }

    public function getFileContentFrom1Password($token_id, $vault_id, $item_id)
    {
        $content = false;
        if (!empty($vault_id)) {
            try {
                $response = $this->get1PasswordConnectResponse($token_id, "/v1/vaults/$vault_id/items/$item_id/files");
                $json = json_decode((string)$response->getBody());
                if (!empty($json[0]->content_path)) {
                    $response = $this->get1PasswordConnectResponse($token_id, $json[0]->content_path);
                    $content = (string)$response->getBody();
                }
            } catch (\Exception $e) {
                $this
                    ->getContext()
                    ->getConfigurationService()
                    ->getLogger()
                    ->warning($e->getMessage());
            }
        }
        if (!$content) {
            $tmp_file = tempnam('/tmp', 'phab-tmp');
            $this->exec1PasswordCli(sprintf('get document %s > "%s"', $item_id, $tmp_file));
            $content = file_get_contents($tmp_file);
            @unlink($tmp_file);
        }

        return $content;
    }

    public function obfuscateSecrets(string $message): string
    {
        $replacements = [];
        foreach ($this->passwords as $password) {
            $replacements[$password] = str_repeat('*', 10);
        }
        return strtr($message, $replacements);
    }
}
