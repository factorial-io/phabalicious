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

    public function resolveSecrets(HostConfig $host_config)
    {
        $replacements = [];
        $this->resolveSecretsImpl($host_config->raw(), $replacements);
        $data = Utilities::expandStrings($host_config->raw(), $replacements);
        $host_config->setData($data);
    }

    private function resolveSecretsImpl(array $data, array &$replacements)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->resolveSecretsImpl($value, $replacements);
            } elseif ($secret_key = $this->containsSecret($value)) {
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

        $pw = $this->getQuestionFactory()->askAndValidate($this->getContext()->io(), $secret_data, null);

        $this->passwords[$secret] = $pw;
        return $pw;
    }
}
