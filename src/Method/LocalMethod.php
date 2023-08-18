<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class LocalMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'local';
    }

    public function supports(string $method_name): bool
    {
        return $method_name == 'local';
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $result = [
            'rootFolder' => $configuration_service->getFabfilePath(),
            'shellProvider' => LocalShellProvider::PROVIDER_NAME,
        ];

        if (!$host_config->has('runLocally')) {
            $result['needs'] = ['local'];
        }

        return new Node($result, $this->getName() . ' method defaults');
    }

    public function validateConfig(
        ConfigurationService $configuration_service,
        Node $config,
        ValidationErrorBagInterface $errors
    ) {

        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->checkForValidFolderName('rootFolder');
        $validation->deprecate([
        'runLocally' => 'Please add `local` to your `needs`!'
        ]);
    }


    public function createShellProvider(array $host_config)
    {
        return ShellProviderFactory::create(LocalShellProvider::PROVIDER_NAME, $this->logger);
    }
}
