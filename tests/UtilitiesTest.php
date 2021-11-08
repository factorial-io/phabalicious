<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 10.09.18
 * Time: 22:03
 */

namespace Phabalicious\Tests;

use Phabalicious\Exception\ArgumentParsingException;
use Phabalicious\Utilities\Utilities;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class UtilitiesTest extends PhabTestCase
{

    public function testMergeData()
    {

        $a = [
            'a' => 1,
            'b' => 2,
            'y' => ['a' => '1', 'b' => '2'],
            'z' => [1, 2, 3]
        ];

        $b = [
            'b' => 3,
            'c' => 4,
            'y' => false,
            'z' => [4, 5, 6]
        ];

        $this->assertEquals([
            'a' => 1,
            'b' => 3,
            'c' => 4,
            'y' => false,
            'z' => [4, 5, 6]
        ], Utilities::mergeData($a, $b));

        $this->assertEquals([
            'a' => 1,
            'b' => 2,
            'c' => 4,
            'y' => ['a' => '1', 'b' => '2'],
            'z' => [1, 2, 3]
        ], Utilities::mergeData($b, $a));

        $this->assertEquals(
            [
                'a' => [
                    0 => 1,
                    1 => 2,
                    'db' => 1
                ],
            ],
            Utilities::mergeData(
                ['a' => [1, 2]],
                ['a' => ['db' => 1]]
            )
        );

        $this->assertEquals(
            [
                'a' => false
            ],
            Utilities::mergeData(
                ['a' => ['a' => 1, 'b' => 2]],
                ['a' => false]
            )
        );

        $this->assertEquals(
            [
                'a' => ['a' => 1, 'b' => 2],
            ],
            Utilities::mergeData(
                ['a' => false],
                ['a' => ['a' => 1, 'b' => 2]]
            )
        );
    }

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
        $result = Utilities::expandStrings($commands, $replacements);

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

    public function testExtractCallback()
    {
        [$callback, $args] = Utilities::extractCallback('execute(docker, run)');
        $this->assertEquals('execute', $callback);
        $this->assertEquals(['docker', 'run'], $args, '', 0.0, 10, true);

        [$callback, $args] = Utilities::extractCallback('execute(deploy)');
        $this->assertEquals('execute', $callback);
        $this->assertEquals(['deploy'], $args, '', 0.0, 10, true);

        $result = Utilities::extractCallback('something is going on');
        $this->assertFalse($result);
    }

    public function testExtractArguments()
    {
        $this->assertEquals(["hello world"], Utilities::extractArguments('hello world'));
        $this->assertEquals(["hello world"], Utilities::extractArguments('"hello world"'));
        $this->assertEquals(["hello world", "10"], Utilities::extractArguments('"hello world", 10'));
        $this->assertEquals(["10", "hello world", "20"], Utilities::extractArguments('10, "hello world", 20'));
        $this->assertEquals(["10", "hello, world", "20"], Utilities::extractArguments('10, "hello, world", 20'));
        $this->assertEquals(
            [1, "  hello, world  ", "foo bar"],
            Utilities::extractArguments('1, "  hello, world  ", foo bar')
        );
    }

    public function testExtractInvalidArguments()
    {
        $this->expectException(ArgumentParsingException::class);
        $this->assertEquals(["hello world", "10"], Utilities::extractArguments('"hello world, 10'));
    }

    public function testExtractInvalidArguments2()
    {
        $this->expectException(ArgumentParsingException::class);
        $this->assertEquals(["hello world", "10"], Utilities::extractArguments('"hello world", "10'));
    }

    public function testSlugify()
    {
        $this->assertEquals('asentencewithoutwords', Utilities::slugify('A sentence without Words'));
        $this->assertEquals('a-sentence-without-words', Utilities::slugify('A sentence without Words', '-'));
    }

    public function testGetNextStableVersion()
    {
        $this->assertEquals("3.6.1", Utilities::getNextStableVersion("3.6.1"));
        $this->assertEquals("3.6", Utilities::getNextStableVersion("3.6"));
        $this->assertEquals("3.6.0", Utilities::getNextStableVersion("3.6.0-beta.1"));
        $this->assertEquals("3.6.10", Utilities::getNextStableVersion("3.6.10-beta.5"));
        $this->assertEquals("3.7.10", Utilities::getNextStableVersion("3.7.10-alpha.5"));
    }

    public function testCleanupString()
    {
        $mappings = [
            "1.0" => "1.0",
            "whatever it takes/foo bar" => "whatever-it-takes-foo-bar",
            "[äöü]" => "-äöü"
        ];

        foreach ($mappings as $input => $result) {
            $this->assertEquals($result, Utilities::cleanupString($input));
        }
    }
}
