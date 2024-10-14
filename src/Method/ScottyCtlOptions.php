<?php

namespace Phabalicious\Method;

use http\Exception\InvalidArgumentException;
use Phabalicious\Configuration\HostConfig;

class ScottyCtlOptions
{

    protected array $data;

    public function __construct(HostConfig $host_config)
    {
        $this->data = [];
        $scotty_data = $host_config->getData()->get('scotty');
        if (!$scotty_data) {
            throw new InvalidArgumentException('Missing scotty configuration');
        }
        foreach (['server', 'app-blueprint', 'access-token', 'basic-auth', 'registry', ] as $key) {
            if ($scotty_data->has($key)) {
                $this->data[$key] = $scotty_data->get($key)->getValue();
            }
        }

        if ($basic_auth = $scotty_data->get('basic-auth')) {
            $this->data['basic-auth'] = sprintf(
                '%s:%s',
                $basic_auth->get('username')->getValue(),
                $basic_auth->get('password')->getValue()
            );
        }

        $this->data['appName'] = $host_config['configName'];
    }

    public function build($app_folder): string
    {
        $options = [
          '--server',
          $this->data['server'],
          'create',
          '--folder',
          $app_folder,
          $this->data['appName'],
        ];
        foreach (['access-token', 'app-blueprint', 'basic-auth', 'registry', ] as $key) {
            if (isset($this->data[$key])) {
                $options[] = '--' . $key;
                $options[] = $this->data[$key];
            }
        }
        return implode(' ', $options);
    }
}
