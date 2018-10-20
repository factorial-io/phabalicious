<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderInterface;

class FilesMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'files';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'files';
    }

    public function putFile(HostConfig $config, TaskContextInterface $context)
    {
        $source = $context->get('sourceFile', false);
        if (!$source) {
            $context->setResult('exitCode', 1);
            return;
        }
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $config->shell());
        $shell->putFile($source, $config['rootFolder'], $context, true);
    }

    public function getFile(HostConfig $config, TaskContextInterface $context)
    {
        $source = $context->get('sourceFile', false);
        $dest = $context->get('destFile', false);
        if (!$source || !$dest) {
            $context->setResult('exitCode', 1);
            return;
        }
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $config->shell());
        $shell->getFile($source, $dest, $context, true);
    }
}