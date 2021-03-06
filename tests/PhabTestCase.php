<?php

namespace Phabalicious\Tests;

use PHPUnit\Framework\TestCase;

class PhabTestCase extends TestCase
{

    protected function getcwd()
    {
        return getcwd() . '/tests';
    }

    protected function checkFileContent($filename, $needle)
    {
        $haystack = file_get_contents($filename);
        $this->assertContains($needle, $haystack);
    }
}
