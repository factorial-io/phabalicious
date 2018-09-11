<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 10.09.18
 * Time: 22:03
 */

namespace Phabalicious\Tests;

use Phabalicious\Utilities\Utilities;
use PHPUnit\Framework\TestCase;

class UtilitiesTest extends TestCase
{

    public function testExpandCommands()
    {
        $replacements = [
            '%one.two%' => 'Example 1',
            '%one.three%' => 'Example 2',
        ];

        $commands = [
            'First %one.two% example',
            'Second %one.two% %one.two% example',
            'This %one.two% %one.three% example'
        ];
        $result = Utilities::expandCommands($commands, $replacements);

        $this->assertEquals([
            'First Example 1 example',
            'Second Example 1 Example 1 example',
            'This Example 1 Example 2 example'
        ], $result);

    }

    public function testExpandVariables()
    {
        $data = [
            'one' => [
                'one' => 'One',
                'two' => 'Two',
                'three' => 'Three',
            ],
            'two' => [
                'one' => 'One',
                'two' => 'Two',
                'three' => [
                    'one' => 'One',
                    'two' => 'Two',
                    'three' => 'Three'
                ]
            ]
        ];
        $result = Utilities::expandVariables($data);

        $this->assertEquals([
            '%one.one%' => 'One',
            '%one.two%' => 'Two',
            '%one.three%' => 'Three',
            '%two.one%' => 'One',
            '%two.two%' => 'Two',
            '%two.three.one%' => 'One',
            '%two.three.two%' => 'Two',
            '%two.three.three%' => 'Three',
        ], $result);
    }
}
