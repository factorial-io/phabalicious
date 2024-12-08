<?php

namespace Phabalicious\ShellProvider\TunnelHelper;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Psr\Log\LoggerInterface;

class TunnelHelperFactory
{
    protected $factory = [];
    protected $tunnels = [];
    protected $logger;
    protected $creatingTunnel = false;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function prepareTunnels($task, HostConfig $config, TaskContextInterface $context)
    {
        if ($this->creatingTunnel) {
            return;
        }
        $this->creatingTunnel = true;

        if ($context->getConfigurationService()->isRunningAppRequired($config, $context, $task)) {
            $this->createLocalToHostTunnel($config, $context);
        }
        if (in_array($task, ['copyFrom'])) {
            $from_config = $context->get('from', false);
            if ($from_config) {
                $this->createLocalToHostTunnel($from_config, $context);
                $this->createHostToHostTunnel($config, $from_config, $context);
            }
        }
        $this->creatingTunnel = false;
    }

    private function getTunnel($tunnel_name): ?TunnelDataInterface
    {
        if (!empty($this->tunnels[$tunnel_name])) {
            $tunnel = $this->tunnels[$tunnel_name];
            if (TunnelDataInterface::CREATED_STATE == $tunnel->getState()) {
                return $tunnel;
            }
        }

        return null;
    }

    private function createLocalToHostTunnel(HostConfig $config, TaskContextInterface $context): ?TunnelDataInterface
    {
        $tunnel_helper_class = $this->getTunnelHelperClass($config);
        if (!$tunnel_helper_class || !$tunnel_helper_class::isConfigSupported($config)) {
            return null;
        }

        $tunnel_name = 'local--'.$config->getConfigName();
        if ($tunnel = $this->getTunnel($tunnel_name)) {
            return $tunnel;
        }

        /** @var LocalToHostTunnelInterface $helper */
        $helper = $this->getOrCreateHelper($tunnel_helper_class);
        if (!$helper instanceof LocalToHostTunnelInterface) {
            return null;
        }
        $this->logger->notice("Creating tunnel $tunnel_name ...");
        $this->tunnels[$tunnel_name] = new TunnelData($tunnel_name, TunnelDataInterface::CREATING_STATE);
        $tunnel = $helper->createLocalToHostTunnel($config, $context, null);

        $this->tunnels[$tunnel_name] = $tunnel;

        return $tunnel;
    }

    private function createHostToHostTunnel(
        HostConfig $source_config,
        HostConfig $target_config,
        TaskContextInterface $context,
    ): ?TunnelDataInterface {
        $tunnel_helper_class = $this->getTunnelHelperClass($source_config);
        if (!$tunnel_helper_class || !$tunnel_helper_class::isConfigSupported($target_config)) {
            return null;
        }

        $tunnel_name = $source_config->getConfigName().'--'.$target_config->getConfigName();

        if ($tunnel = $this->getTunnel($tunnel_name)) {
            return $tunnel;
        }

        /** @var HostToHostTunnelInterface $helper */
        $helper = $this->getOrCreateHelper($tunnel_helper_class);
        if (!$helper instanceof HostToHostTunnelInterface) {
            return null;
        }
        $this->logger->info("Creating tunnel $tunnel_name ...");
        $this->tunnels[$tunnel_name] = new TunnelData($tunnel_name, TunnelDataInterface::CREATING_STATE);
        $tunnel = $helper->createHostToHostTunnel($source_config, $target_config, $context, null);

        $this->tunnels[$tunnel_name] = $tunnel;

        return $tunnel;
    }

    private function getTunnelHelperClass(HostConfig $config)
    {
        if ($config->shell() instanceof TunnelSupportInterface) {
            /** @var TunnelSupportInterface $shell */
            $shell = $config->shell();

            return $shell->getTunnelHelperClass();
        }

        return false;
    }

    private function getOrCreateHelper(string $tunnel_helper_class)
    {
        if (empty($this->factory[$tunnel_helper_class])) {
            $this->factory[$tunnel_helper_class] = new $tunnel_helper_class($this->logger);
        }

        return $this->factory[$tunnel_helper_class];
    }
}
