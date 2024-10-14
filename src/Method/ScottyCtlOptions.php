<?php

namespace Phabalicious\Method;

use InvalidArgumentException;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Utilities\Utilities;

class ScottyCtlOptions
{

    protected array $data;

    public function __construct(
        protected readonly HostConfig $hostConfig,
        protected readonly TaskContextInterface $context
    ) {
        $this->data = [];
        $scotty_data = $hostConfig->getData()->get('scotty');
        if (!$scotty_data) {
            throw new InvalidArgumentException('Missing scotty configuration');
        }

        foreach (['server', 'access-token'] as $key) {
            if ($scotty_data->has($key)) {
                $this->data[$key] = $scotty_data->get($key)->getValue();
            }
        }

        $this->data['appName'] = $hostConfig['configName'];
    }

    public function build($command, $additional_data = []): array {
        $variables = Utilities::buildVariablesFrom($this->hostConfig, $this->context);
        $replacements = Utilities::expandVariables($variables);

        $data = Utilities::expandStrings(array_merge($this->data, $additional_data), $replacements);

        return $this->buildImpl($data, $command);
    }

    protected function buildImpl(array $data, string $command): array {

        $options = [
          '--server',
          $this->data['server'],
        ];
        if ($data['access-token']) {
            $options[] = '--access-token';
            $options[] = $data['access-token'];
        }
        $options[] = $command;
        $options[] = $data['appName'];

        return $options;
    }
}
