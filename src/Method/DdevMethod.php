<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class DdevMethod extends BaseMethod implements MethodInterface
{
    public function getName(): string
    {
        return 'ddev';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
    }

    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        $node = new Node([], $this->getName().' global settings');
        $config_file = $configuration->getFabfilePath().'/.ddev/config.yaml';
        if (file_exists($config_file)) {
            $data = Node::parseYamlFile($config_file);
            $node->set('ddev', $data);
        }

        return $node;
    }

    public function validateGlobalSettings(Node $settings, ValidationErrorBagInterface $errors)
    {
        if ($settings->has('ddev')) {
            $ddev = $settings['ddev'];
            $service = new ValidationService($ddev, $errors, 'ddev settings');
            $service->hasKey('name', 'the ddev project-name is missing');
        }
    }

    public function alterConfig(ConfigurationService $configuration_service, Node $data)
    {
        $tokens = [
            'global' => Utilities::getGlobalReplacements($configuration_service),
            'settings' => $configuration_service->getAllSettings(),
            'host' => $data->asArray(),
        ];
        $replacements = Utilities::expandVariables($tokens);
        // Apply replacements to info and docker keys.
        foreach (['info', 'docker'] as $key) {
            if ($data->has($key)) {
                $data->get($key)->expandReplacements($replacements, []);
            }
        }
    }

    public function getMethodDependencies(MethodFactory $factory, \ArrayAccess $data): array
    {
        return [
            DockerMethod::METHOD_NAME,
        ];
    }
}
