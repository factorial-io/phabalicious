<?php

namespace Phabalicious\Configuration;

use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;

class BlueprintTemplate
{

    /** @var array */
    private $template;

    /** @var array */
    private $parent;

    /** @var ConfigurationService  */
    private $configuration;

    protected $name;

    /**
     * BlueprintTemplate constructor.
     *
     * @param $name
     * @param ConfigurationService $service
     * @param \Phabalicious\Configuration\Storage\Node $data
     * @param \Phabalicious\Configuration\Storage\Node $parent
     */
    public function __construct($name, ConfigurationService $service, Node $data, Node $parent)
    {
        $this->name = $name;
        $this->configuration = $service;
        $this->template = $data;
        $this->parent = $parent;
    }

    public function expand($identifier)
    {
        $project_name = $this->configuration->getSetting('name', 'unknown');
        $project_key = $this->configuration->getSetting('key', substr($project_name, 0, 3));
        $identifier_wo_feature = str_replace('feature/', '', $identifier);
        $identifier_wo_prefix = basename($identifier);

        $replacements = Utilities::expandVariables([
            'template' => $this->template->getValue(),
            'parent' => $this->parent->getValue()
        ]);

        $replacements['%identifier%'] = $identifier;
        $replacements['%slug%'] = Utilities::slugify($identifier);
        $replacements['%slug.without-feature%'] = Utilities::slugify($identifier_wo_feature);
        $replacements['%slug.without-prefix%'] = Utilities::slugify($identifier_wo_prefix);
        $replacements['%slug.with-hyphens%'] = Utilities::slugify($identifier, '-');
        $replacements['%slug.with-hyphens.without-prefix%'] = Utilities::slugify($identifier_wo_prefix, '-');
        $replacements['%slug.with-hyphens.without-feature%'] = Utilities::slugify($identifier_wo_feature, '-');
        $replacements['%project-identifier%'] = $project_name;
        $replacements['%project-slug%'] = Utilities::slugify($project_name);
        $replacements['%project-slug.with-hypens%'] = Utilities::slugify($project_name, '-');
        $replacements['%project-slug.with-hyphens%'] = Utilities::slugify($project_name, '-');
        $replacements['%project-key%'] = Utilities::slugify($project_key);
        $replacements['%fabfilePath%'] = $this->configuration->getFabfilePath();

        return new Node(
            Utilities::expandStrings($this->template->getValue(), $replacements),
            sprintf('blueprint %s expanded from %s', $this->name, $identifier)
        );
    }

    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param \Phabalicious\Configuration\Storage\Node $templates
     *
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     */
    public function resolveInheritance(Node $templates)
    {
        if (isset($this->template['blueprintInheritsFrom'])) {
            $this->template = $this->configuration->resolveInheritance(
                $this->template,
                $templates,
                false,
                [],
                'blueprintInheritsFrom'
            );
        }
    }
}
