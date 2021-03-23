<?php


namespace Phabalicious\Utilities;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\UnknownSecretException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Questions\Question;
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
            } elseif (is_string($value) && ($secret_key = $this->containsSecret($value))) {
                $replacements['%secret.' . $secret_key . '%'] = $this->getSecret($secret_key);
            }
        }
    }

    private function containsSecret(string $string)
    {
        $matches = [];
        if (preg_match("/%secret\.(.*)%/", $string, $matches)) {
            return $matches[1];
        }
        return false;
    }

    private function getSecret($secret)
    {
        $secrets = $this
            ->getContext()
            ->getConfigurationService()
            ->getSetting('secrets', []);
        if (!isset($secrets[$secret])) {
            throw new UnknownSecretException("Could not find secret `$secret` in config!");
        }

        if (isset($this->passwords[$secret])) {
            return $this->passwords[$secret];
        }

        $secret_data = $secrets[$secret];

        $env_name = !empty($secret_data['env']) ? $secret_data['env'] : Utilities::toUpperSnakeCase($secret);
        if ($value = getenv($env_name)) {
            return $value;
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

        if (isset($secret_data['onePasswordId'])) {
            $pw = $this->getSecretFrom1Password($secret_data['onePasswordId']);
            if ($pw) {
                return $pw;
            }
        }

        $pw = $this->getQuestionFactory()->askAndValidate($this->getContext()->io(), $secret_data, null);

        $this->passwords[$secret] = $pw;
        return $pw;
    }

    private function getSecretFrom1Password($item_id)
    {
        $op_file_path = getenv('PHAB_OP_FILE_PATH') ?: '/usr/local/bin/op';
        if (!$op_file_path || !file_exists($op_file_path)) {
            return false;
        }

        $output = [];
        $result_code = 0;
        $result = exec(sprintf("%s get item %s", $op_file_path, $item_id), $output, $result_code);
        if ($result_code == 0) {
            $json = json_decode(implode("\n", $output));

            if (!empty($json->details->fields)) {
                $fields = $json->details->fields;
                foreach ($fields as $field) {
                    if ($field->designation == 'password') {
                        return $field->value;
                    }
                }
            }
        } else {
            throw new \RuntimeException("1Password returned an error, are you logged in?");
        }

        return false;
    }
}
