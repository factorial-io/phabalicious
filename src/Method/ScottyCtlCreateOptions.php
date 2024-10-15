<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Webmozart\Assert\Assert;

class ScottyCtlCreateOptions extends ScottyCtlOptions
{

    public function __construct(HostConfig $host_config, TaskContextInterface $context)
    {
        parent::__construct($host_config, $context);

        $scotty_data = $host_config->getData()->get('scotty');
        Assert::isInstanceOf($scotty_data, Node::class);

        foreach (['app-blueprint', 'basic-auth', 'registry'] as $key) {
            if ($scotty_data->has($key)) {
                $this->data[$key] = $scotty_data->get($key)->getValue();
            }
        }

        foreach (['services', 'environment'] as $key) {
            if ($scotty_data->has($key)) {
                $this->data[$key] = $scotty_data->get($key)->asArray();
            }
        }

        if ($basic_auth = $scotty_data->get('basic-auth')) {
            $this->data['basic-auth'] = sprintf(
                '%s:%s',
                $basic_auth->get('username')->getValue(),
                $basic_auth->get('password')->getValue()
            );
        }
    }

    public function runInShell(ShellProviderInterface $shell, string $command, array $add_data): CommandResult
    {
        return $shell->run(sprintf(
            '#!scottyctl %s',
            implode(' ', $this->build($command, $add_data))
        ));
    }

    protected function buildImpl(array $data, string $command): array
    {
        $options = [
        '--folder',
          $data['app_folder'],
        ];
        $mapping = ['services' => 'service', 'environment' => 'env'];
        foreach (['services' => ':', 'environment' => '='] as $key => $separator) {
            if (isset($data[$key])) {
                foreach ($data[$key] as $subkey => $value) {
                    $options[] = '--' . $mapping[$key];
                    $options[] = $subkey . $separator . $value;
                }
            }
        }
        foreach (['basic-auth', 'app-blueprint', 'registry', ] as $key) {
            if (isset($data[$key])) {
                $options[] = '--' . $key;
                $options[] = $this->data[$key];
            }
        }

        return array_merge(parent::buildImpl($data, $command), $options);
    }
}
