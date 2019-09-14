<?php

namespace Phabalicious\Tests;

use PHPUnit\Framework\TestCase;

class PhabTestCase extends TestCase
{

    protected function getcwd()
    {
        return getcwd() . '/tests';
    }
}
