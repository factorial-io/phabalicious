<?php

namespace Phabalicious\Scaffolder\TwigExtensions;

use Phabalicious\Utilities\PasswordManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class EncryptExtension extends AbstractExtension
{
    /**
     * @var PasswordManagerInterface
     */
    private $passwordManager;

    public function __construct(PasswordManagerInterface $password_manager)
    {
        $this->passwordManager = $password_manager;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('encrypt', [$this, 'encrypt']),
            new TwigFilter('decrypt', [$this, 'decrypt']),
        ];
    }

    public function getName(): string
    {
        return 'encrypt';
    }

    public function encrypt($data, $secret_name)
    {
        return $this->passwordManager->encrypt($data, $secret_name);
    }

    public function decrypt($data, $secret_name)
    {
        return $this->passwordManager->decrypt($data, $secret_name);
    }
}
