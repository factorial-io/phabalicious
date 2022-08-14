<?php

namespace Phabalicious\Method;

use Phabalicious\Artifact\Actions\ActionFactory;
use Phabalicious\Artifact\Actions\Ftp\ExcludeAction;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Utilities\AppDefaultStages;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;

class ArtifactsCustomMethod extends ArtifactsBaseMethod implements MethodInterface
{

    const STAGES_KEY = 'artifact.stages';

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        ActionFactory::register($this->getName(), 'exclude', ExcludeAction::class);
    }

    public function getName(): string
    {
        return 'artifacts--custom';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $return = [];
        $return['tmpFolder'] = '/tmp';
        $return['deployMethod'] = $this->getName();
        $return['artifact'] = [
            'useLocalRepository' => false,
        ];

        return $parent->merge(new Node($return, $this->getName() . ' method defaults'));
    }

    /**
     * @param \Phabalicious\Configuration\Storage\Node $config
     * @param ValidationErrorBagInterface $errors
     */
    public function validateConfig(Node $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);
        $validation = new ValidationService($config, $errors, "artifact settings");
        $validation->hasKey(self::STAGES_KEY, '`stages` is required.');
        $validation->isArray(self::STAGES_KEY, '`stages` should be an array');
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     *
     * @throws \Phabalicious\Exception\FailedShellCommandException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    public function deploy(HostConfig $host_config, TaskContextInterface $context)
    {

        $stages = $host_config->getProperty(self::STAGES_KEY);
        $stages = $this->prepareDirectoriesAndStages($host_config, $context, $stages, true);

        $this->buildArtifact($host_config, $context, $stages);

        $this->cleanupDirectories($host_config, $context);
        // Do not run any next tasks.
        $context->setResult('runNextTasks', []);
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runStageSteps($host_config, $context, []);
    }
}
