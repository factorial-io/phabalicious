<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class ScriptMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'script';
    }

    public function supports(string $method_name): bool
    {
        return $method_name = 'script';
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array {
        return [
            'rootFolder' => $configuration_service->getFabfilePath(),
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors) {
        $service = new ValidationService($config, $errors, 'host-config');
        $service->hasKey('rootFolder', 'The root-folder of your configuration.');
    }


    public function runScript(HostConfig $host_config, TaskContext $context)
    {
        $commands = $context->get('scriptData', []);
        $variables = $context->get('variables', []);
        $callbacks = $context->get('callbacks', []);
        $environment = $context->get('environment', []);
        $root_folder = isset($host_config['siteFolder'])
            ? $host_config['siteFolder']
            : isset($host_config['rootFolder'])
                ? $host_config['rootFolder']
                : '.';

        if (!empty($host_config['environment'])) {
            $environment = Utilities::mergeData($environment, $host_config['environment']);
        }
        $variables = [
            'variables' => $variables,
            'host' => $host_config->raw(),
            'settings' => $context->getConfigurationService()->getAllSettings(['hosts', 'dockerHosts']),
        ];

        $replacements = Utilities::expandVariables($variables);
        $commands = Utilities::expandStrings($commands, $replacements);
        $commands = Utilities::expandStrings($commands, $replacements);
        $environment = Utilities::expandStrings($environment, $replacements);

        $this->runScriptImpl($root_folder, $commands, $host_config, $callbacks, $environment, $replacements);

    }

    private function runScriptImpl(
        string $root_folder,
        array $commands,
        HostConfig $host_config,
        array $callbacks = [],
        array $environment = [],
        array $replacements = []
    )
    {
    }

}