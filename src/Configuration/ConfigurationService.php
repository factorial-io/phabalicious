<?php

namespace Phabalicious\Configuration;

use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use phpDocumentor\Reflection\Types\Mixed_;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Wikimedia\Composer\Merge\NestedArray;

class ConfigurationService
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private $fabfilePath;

    private $dockerHosts;
    private $hosts;
    private $settings;

    public function __construct()
    {
    }

    public function readConfiguration(string $path, string $override = ''): bool
    {
        if (!empty($this->settings)) {
            return true;
        }

        $fabfile = !empty($override) ? $override : $this->findFabfilePath([
            'fabfile.yml',
            '.fabfile.yml',
            'fabfile.yaml',
            '.fabfile.yaml'
        ], $path);

        if (!$fabfile || !file_exists($fabfile)) {
            if (!empty($override)) {
                throw new FabfileNotFoundException("Could not find fabfile at '" . $override . "'");
            } else {
                throw new FabfileNotFoundException("Could not find any fabfile at '" . $path . "'");
            }
        }

        $this->setFabfilePath(dirname($fabfile));

        $data = $this->readFile($fabfile);
        if (!$data) {
            throw new FabfileNotReadableException("Could not read from '" . $fabfile . "'");
        }

        if ($local_override_file = $this->findFabfilePath(['fabfile_local.yaml', 'fabfile_local.yml'])) {
            $override_data = $this->readFile($local_override_file);
            $data = $this->mergeData($data, $override_data);
        }
        $data = $this->resolveInheritance($data);

        return true;
    }

    protected function findFabfilePath(array $candidates, string $path = '')
    {
        if (empty($path)) {
            $path = $this->getFabfilePath();
        }
        $depth = 0;
        while ($depth <= 3) {
            foreach ($candidates as $candidate) {
                if (file_exists($path . '/' . $candidate)) {
                    return $path . '/' . $candidate;
                }
            }
            $depth++;
            $path = dirname($path);
        }

        return false;
    }

    protected function readFile(string $file)
    {
        return Yaml::parseFile($file);
    }

    private function setFabfilePath(string $path) {
        $this->fabfilePath = $path;
    }

    public function getFabfilePath() {
        return $this->fabfilePath;
    }

    private function mergeData(array $data, array $override_data): array
    {
        return NestedArray::mergeDeep($data, $override_data);
    }

    private function resolveInheritance(array $data): array
    {
        return $data;
    }

}