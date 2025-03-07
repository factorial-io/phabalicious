<?php

namespace Phabalicious\Scaffolder\TwigExtensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class IndentExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('indent', [$this, 'indent']),
        ];
    }

    public function getName(): string
    {
        return 'indent';
    }

    public function indent($text, $indentation = 4)
    {
        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            $line = $line ? str_repeat(' ', $indentation).trim($line) : '';
        }

        return implode("\n", $lines);
    }
}
