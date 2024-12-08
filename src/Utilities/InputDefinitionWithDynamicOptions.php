<?php

namespace Phabalicious\Utilities;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class InputDefinitionWithDynamicOptions extends InputDefinition
{
    protected array $dynamicOptions = [];

    public function __construct(InputDefinition $definition)
    {
        parent::__construct();
        $this->setArguments($definition->getArguments());
        $this->setOptions($definition->getOptions());
    }

    public function getOption($name)
    {
        if (!parent::hasOption($name)) {
            $this->addOption(new InputOption($name, null, InputOption::VALUE_OPTIONAL));
            $this->dynamicOptions[] = $name;
        }

        return parent::getOption($name);
    }

    public function hasOption($name): bool
    {
        return true;
    }

    public function getDynamicOptions(): array
    {
        return $this->dynamicOptions;
    }
}
