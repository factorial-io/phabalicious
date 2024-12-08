<?php

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 28.10.18
 * Time: 13:16.
 */

namespace Phabalicious\Configuration;

class HostType
{
    public const DEV = 'dev';
    public const STAGE = 'stage';
    public const PROD = 'prod';
    public const TEST = 'test';

    public static function getAll()
    {
        return [
            self::DEV,
            self::STAGE,
            self::PROD,
            self::TEST,
        ];
    }

    public static function convertLegacyTypes($type)
    {
        if ('live' == $type) {
            return self::PROD;
        }

        return $type;
    }
}
