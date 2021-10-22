<?php

namespace Phabalicious\Configuration;

use http\Exception\RuntimeException;
use Phabalicious\Utilities\Utilities;

class HostConfigurationCategory
{

    protected $id;
    protected $label;

    public static $categories = [];

    public function __construct($id, $label)
    {
        $this->id = $id;
        $this->label = $label;
    }

    public static function getOrCreate($data)
    {
        if (is_array($data)) {
            $id = $data['id'];
            $label = $data['label'];
        } else {
            $id = Utilities::slugify($data, '-');
            $label = $data;
        }

        if ($category = self::get($id)) {
            return $category;
        }
        $category = new self($id, $label);
        self::$categories[$category->getId()] = $category;

        return $category;
    }

    public static function get(string $category_id): ?HostConfigurationCategory
    {
        return self::$categories[$category_id] ?? null;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }
}
