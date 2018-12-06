<?php


namespace Phabalicious\Utilities;

use http\Exception\RuntimeException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Yaml\Yaml;

class PasswordManager implements PasswordManagerInterface
{

    private $context;

    private $passwords;

    public function __construct(TaskContextInterface $context)
    {
        $this->context = $context;
        $this->readPasswords();
    }

    public function getPasswordFor(string $host, int $port, string $user)
    {
        $key = $this->getKey($host, $port, $user);
        if (!empty($this->passwords[$key])) {
            return $this->passwords[$key];
        }

        $pw = $this->context->askQuestion(sprintf('Please provide a password for `%s@%s`: ', $user, $host));
        $this->passwords[$key] = $pw;
        return $pw;
    }

    private function getKey($host, $port, $user)
    {
        return sprintf('%s@%s:%s', $user, $host, $port);
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

}