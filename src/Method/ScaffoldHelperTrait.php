<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Scaffolder\Callbacks\CopyAssetsBaseCallback;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Scaffolder\Scaffolder;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;

trait ScaffoldHelperTrait
{
    protected function getScaffoldDefaultConfig($host_config, $config, $key)
    {
        if (!empty($host_config[$key]['scaffold']) || !empty($config[$key]['scaffold'])) {
            $config[$key]['scaffold'] = Utilities::mergeData([
               'scaffold'  => [
                   'copy_assets(%rootFolder%)'
               ],
               'questions' => [],
               'successMessage' => "Scaffolded files for $key successfully!",
            ], $config[$key]['scaffold'] ?? []);
        } else {
            $config[$key]['scaffold'] = false;
        }

        return $config[$key]['scaffold'];
    }

    protected function validateScaffoldConfig($config, $key, ValidationErrorBag $errors): void
    {
        if ($config[$key]['scaffold']) {
            $validation = new ValidationService(
                $config[$key]['scaffold'],
                $errors,
                sprintf('host.%s.scaffold: `%s`', $key, $config['configName'])
            );
            $validation->hasKeys([
               'assets' => 'The list of assets to scaffold',
               'scaffold' => 'The scaffolding script',
               'questions' => 'The questions to ask before scaffolding',
            ]);
        }
    }

    /**
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\FailedShellCommandException
     * @throws \Phabalicious\Exception\YamlParseException
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function runScaffolder(HostConfig $host_config, TaskContextInterface $context, ShellProviderInterface $shell, string $project_folder, string $key): void
    {

        $scaffold_definition = $host_config->getData()->get($key)?->get('scaffold');
        if (!$scaffold_definition || !$scaffold_definition->getValue()) {
            throw new \RuntimeException(sprintf(
                "Configuration `%s` does not support scaffolded $key configuration",
                $host_config->getConfigName()
            ));
        }
        $tokens = Utilities::buildVariablesFrom($host_config, $context);
        unset($tokens['context']);

        $options = new Options();
        $options
           ->setRootPath($context->getConfigurationService()->getFabfilePath())
           ->setShell($shell)
           ->setQuiet(true)
           ->setSkipSubfolder(true)
           ->setAllowOverride(true)
           ->setUseCacheTokens(false)
           ->setScaffoldDefinition($scaffold_definition);

        $context->set('scaffoldStrategy', CopyAssetsBaseCallback::IGNORE_SUBFOLDERS_STRATEGY);
        $scaffolder = new Scaffolder($context->getConfigurationService());

        $scaffolder->scaffold(
            false,
            $project_folder,
            $context,
            $tokens,
            $options
        );
    }
}
