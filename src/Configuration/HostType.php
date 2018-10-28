<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 28.10.18
 * Time: 13:16
 */

namespace Phabalicious\Configuration;

class HostType
{
    const DEV = 'dev';
    const STAGE = 'stage';
    const PROD = 'prod';
    const TEST = 'test';

    public static function getAll()
    {
        return [
            self::DEV,
            self::STAGE,
            self::PROD,
            self::TEST
        ];
    }

}