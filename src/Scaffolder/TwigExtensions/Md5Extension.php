<?php

namespace Phabalicious\Scaffolder\TwigExtensions;

use Twig\Extension\AbstractExtension;

class Md5Extension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new \Twig\TwigFilter('md5', 'md5'),
        ];
    }

    public function getName(): string
    {
        return "md5";
    }
}
