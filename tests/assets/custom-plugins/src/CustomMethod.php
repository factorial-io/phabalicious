<?php

namespace Phabalicious\CustomPlugin;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Method\BaseMethod;
use Phabalicious\Method\MethodInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return "custom";
    }

    public function supports(string $method_name): bool
    {
        return $method_name == $this->getName();
    }
}
