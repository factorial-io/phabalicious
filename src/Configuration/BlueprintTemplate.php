<?php

namespace Phabalicious\Configuration;

use Phabalicious\Utilities\Utilities;

class BlueprintTemplate
{

    /** @var array */
    private $template;

    /** @var ConfigurationService  */
    private $configuration;

    /**
     * BlueprintTemplate constructor.
     * @param ConfigurationService $service
     * @param array $data
     */
    public function __construct(ConfigurationService $service, $data)
    {
        $this->configuration = $service;
        $this->template = $data;
    }

    public function expand($identifier)
    {
        $project_name = $this->configuration->getSetting('name', 'unknown');
        $project_key = $this->configuration->getSetting('key', substr($project_name, 0, 3));
        $identifier_wo_feature = str_replace('feature/', '', $identifier);

        $replacements = [];
        $replacements['%identifier%'] = $identifier;
        $replacements['%slug%'] = Utilities::slugify($identifier);
        $replacements['%slug.with-hyphens%'] = Utilities::slugify($identifier, '-');
        $replacements['%slug.without-feature%'] = Utilities::slugify($identifier_wo_feature);
        $replacements['%slug.with-hyphens.without-feature%'] = Utilities::slugify($identifier_wo_feature, '-');
        $replacements['%project-identifier%'] = $project_name;
        $replacements['%project-slug%'] = Utilities::slugify($project_name);
        $replacements['%project-slug.with-hypens%'] = Utilities::slugify($project_name, '-');
        $replacements['%project-key%'] = Utilities::slugify($project_key, '');

        return Utilities::expandStrings($this->template, $replacements);
    }
}
