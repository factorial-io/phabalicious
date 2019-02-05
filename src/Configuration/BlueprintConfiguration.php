<?php

namespace Phabalicious\Configuration;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;

class BlueprintConfiguration
{

    /** @var BlueprintTemplate[] */
    private $templates = [];

    /** @var ConfigurationService */
    private $configuration;

    public function __construct(ConfigurationService $service)
    {
        $this->configuration = $service;
        if ($data = $this->configuration->getSetting('blueprint', false)) {
            $this->templates['default'] = new BlueprintTemplate($this->configuration, $data);
        }

        foreach ($this->configuration->getAllDockerConfigs() as $key => $data) {
            $data = $this->configuration->getDockerConfig($key);
            if (!empty($data['blueprint'])) {
                $this->templates['docker:'  . $key] = new BlueprintTemplate($this->configuration, $data['blueprint']);
            }
        }
        foreach ($this->configuration->getAllHostConfigs() as $key => $data) {
            if (!empty($data['blueprint'])) {
                $this->templates['host:'  . $key] = new BlueprintTemplate($this->configuration, $data['blueprint']);
            }
        }
    }


    /**
     * Get a template by key.
     * @param $key
     * @return BlueprintTemplate
     * @throws BlueprintTemplateNotFoundException
     */
    public function getTemplate($key = 'default'): BlueprintTemplate
    {
        if (isset($this->templates['host:' . $key])) {
            return $this->templates['host:' . $key];
        }
        if (isset($this->templates['docker:' . $key])) {
            return $this->templates['docker:' . $key];
        }
        if (isset($this->templates['default'])) {
            return $this->templates['default'];
        }

        throw new BlueprintTemplateNotFoundException('Could not find blueprint template with key `' . $key . '`');
    }

    public function getTemplates()
    {
        return $this->templates;
    }

    /**
     * Expand variants.
     *
     * @param $blueprints
     * @throws BlueprintTemplateNotFoundException
     * @throws ValidationFailedException
     */
    public function expandVariants($blueprints)
    {
        $errors = new ValidationErrorBag();
        foreach ($blueprints as $blueprint) {
            $validation = new ValidationService($blueprint, $errors, 'blueprints');

            $validation->hasKey('configName', 'A blueprint needs a config_name');
            $validation->isArray('variants', '`variants` not found or not an array!');
        }
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        foreach ($blueprints as $data) {
            $template = $this->getTemplate($data['configName']);
            foreach ($data['variants'] as $variant) {
                $this->configuration->addHost($template->expand($variant));
            }
        }
    }


    /**
     * Get all variants for a given config.
     *
     * @param $config_name
     * @return bool|array
     */
    public function getVariants($config_name)
    {
        $data = $this->configuration->getSetting('blueprints', []);
        foreach ($data as $b) {
            if ($b['configName'] == $config_name) {
                return $b['variants'];
            }
        }

        return false;
    }
}
