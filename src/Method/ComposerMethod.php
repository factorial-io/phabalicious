<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class ComposerMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'composer';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'composer';
    }

    public function getGlobalSettings(): array
    {
        return [
            'executables' => [
                'composer' => 'composer',
            ],
        ];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            'composerRootFolder' => isset($host_config['gitRootFolder'])
                ? $host_config['gitRootFolder']
                : $host_config['rootFolder'],
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKey('composerRootFolder', 'composerRootFolder should point to your composer root folder.');
    }


    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    public function resetPrepare(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $host_config->shell();
        $shell->cd($host_config['composerRootFolder']);
        $options = '';
        if (!in_array($host_config['type'], array('dev', 'test'))) {
            $options = ' --no-dev --optimize-autoloader';
        }
        $shell->run('#!composer install ' . $options);
    }

}