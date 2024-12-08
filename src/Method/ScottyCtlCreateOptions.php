<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Webmozart\Assert\Assert;

class ScottyCtlCreateOptions extends ScottyCtlOptions
{
    public const VALUE_PARAMS = ['basic-auth', 'app-blueprint', 'registry', 'ttl'];
    public const BOOL_PARAMS = ['allow-robots'];
    public const COMPLEX_PARAMS = ['services', 'environment', 'custom-domains'];

    public function __construct(HostConfig $host_config, TaskContextInterface $context)
    {
        parent::__construct($host_config, $context);

        $scotty_data = $host_config->getData()->get('scotty');
        Assert::isInstanceOf($scotty_data, Node::class);

        foreach (array_merge(self::VALUE_PARAMS, self::BOOL_PARAMS) as $key) {
            if ($scotty_data->has($key)) {
                $this->data[$key] = $scotty_data->get($key)->getValue();
            }
        }

        foreach (self::COMPLEX_PARAMS as $key) {
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

    protected function buildImpl(array $data, string $command): array
    {
        $options = [
            '--folder',
            $data['app_folder'],
        ];
        $mapping = [
            'services' => 'service',
            'environment' => 'env',
            'custom-domains' => 'custom-domain',
        ];
        $separators = [
            'services' => ':',
            'environment' => '=',
            'custom-domains' => ':',
        ];
        foreach ($separators as $key => $separator) {
            if (isset($data[$key])) {
                foreach ($data[$key] as $subkey => $value) {
                    $options[] = '--'.$mapping[$key];
                    $options[] = $subkey.$separator.$value;
                }
            }
        }
        foreach (self::VALUE_PARAMS as $key) {
            if (isset($data[$key])) {
                $options[] = '--'.$key;
                $options[] = $this->data[$key];
            }
        }

        foreach (self::BOOL_PARAMS as $key) {
            if (!empty($data[$key])) {
                $options[] = '--'.$key;
            }
        }

        return array_merge(parent::buildImpl($data, $command), $options);
    }
}
