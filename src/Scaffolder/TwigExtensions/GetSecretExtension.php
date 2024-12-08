<?php

namespace Phabalicious\Scaffolder\TwigExtensions;

use Phabalicious\Utilities\PasswordManagerInterface;
use Twig\Extension\AbstractExtension;

class GetSecretExtension extends AbstractExtension
{
    /**
     * @var PasswordManagerInterface
     */
    private $passwordManager;

    public function __construct(PasswordManagerInterface $password_manager)
    {
        $this->passwordManager = $password_manager;
    }

    public function getFunctions(): array
    {
        return [
            new \Twig\TwigFunction('secret', [$this, 'getSecret']),
        ];
    }

    public function getName(): string
    {
        return 'secret';
    }

    public function getSecret($secret_name)
    {
        return $this->passwordManager->getSecret($secret_name);
    }
}
