<?php

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 10.09.18
 * Time: 22:03.
 */

namespace Phabalicious\Tests;

use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Configuration\Storage\Store;
use Phabalicious\Exception\ArgumentParsingException;
use Phabalicious\Utilities\Utilities;

class UtilitiesTest extends PhabTestCase
{
    public function testMergeData(): void
    {
        $a = [
            'a' => 1,
            'b' => 2,
            'y' => ['a' => '1', 'b' => '2'],
            'z' => [1, 2, 3],
        ];

        $b = [
            'b' => 3,
            'c' => 4,
            'y' => false,
            'z' => [4, 5, 6],
        ];

        $this->assertEquals([
            'a' => 1,
            'b' => 3,
            'c' => 4,
            'y' => false,
            'z' => [4, 5, 6],
        ], Utilities::mergeData($a, $b));

        $this->assertEquals([
            'a' => 1,
            'b' => 2,
            'c' => 4,
            'y' => ['a' => '1', 'b' => '2'],
            'z' => [1, 2, 3],
        ], Utilities::mergeData($b, $a));

        $this->assertEquals(
            [
                'a' => [
                    0 => 1,
                    1 => 2,
                    'db' => 1,
                ],
            ],
            Utilities::mergeData(
                ['a' => [1, 2]],
                ['a' => ['db' => 1]]
            )
        );

        $this->assertEquals(
            [
                'a' => false,
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

    public function testNodeMergeData(): void
    {
        $a = new Node([
            'a' => 1,
            'b' => 2,
            'y' => ['a' => '1', 'b' => '2'],
            'z' => [1, 2, 3],
        ], 'a');

        $b = new Node([
            'b' => 3,
            'c' => 4,
            'y' => false,
            'z' => [4, 5, 6],
        ], 'b');

        $this->assertEquals([
            'a' => 1,
            'b' => 3,
            'c' => 4,
            'y' => false,
            'z' => [4, 5, 6],
        ], Node::mergeData($a, $b)->asArray());

        $this->assertEquals([
            'a' => 1,
            'b' => 2,
            'c' => 4,
            'y' => ['a' => '1', 'b' => '2'],
            'z' => [1, 2, 3],
        ], Node::mergeData($b, $a)->asArray());

        $this->assertEquals(
            [
                'a' => [
                    0 => 1,
                    1 => 2,
                    'db' => 1,
                ],
            ],
            Node::mergeData(
                new Node(['a' => [1, 2]], 'a'),
                new Node(['a' => ['db' => 1]], 'b'),
            )->asArray()
        );

        $this->assertEquals(
            [
                'a' => false,
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

    public function testProtectedProperties(): void
    {
        $a = new Node([
            'protected' => [
                'foo.bar',
                'foo.foobar',
                'bar.two',
            ],
            'foo' => [
                'bar' => 'I am protected',
                'baz' => 'I will be overridden',
                'foobar' => 'I am also protected',
            ],
            'bar' => [
                'one' => 1,
                'two' => 2,
            ],
        ], 'a');

        $b = new Node([
            'foo' => [
                'bar' => 'Does not matter',
                'baz' => 'I am overridden',
                'foobar' => 'Does not matter either',
            ],
            'bar' => [
                'one' => 11,
                'two' => 22,
            ],
        ], 'b');

        $result = Node::mergeData($a, $b);

        $this->assertEquals('Does not matter', $result->getProperty('foo.bar'));
        $this->assertEquals('I am overridden', $result->getProperty('foo.baz'));
        $this->assertEquals('Does not matter either', $result->getProperty('foo.foobar'));
        $this->assertEquals(11, $result->getProperty('bar.one'));
        $this->assertEquals(22, $result->getProperty('bar.two'));

        Store::setProtectedProperties($a, 'protected');

        $result = Node::mergeData($a, $b);

        $this->assertEquals('I am protected', $result->getProperty('foo.bar'));
        $this->assertEquals('I am overridden', $result->getProperty('foo.baz'));
        $this->assertEquals('I am also protected', $result->getProperty('foo.foobar'));
        $this->assertEquals(11, $result->getProperty('bar.one'));
        $this->assertEquals(2, $result->getProperty('bar.two'));

        Store::resetProtectedProperties();
    }

    public function testNodeBaseOntop(): void
    {
        $a = new Node([
            'a' => [0, 1],
            'b' => [
                'a' => 'foo',
                'b' => 'bar',
                'c' => 'foobar',
            ],
            'c' => [0, 1],
        ], 'config');

        $b = new Node([
            'a' => 0,
            'b' => [
                'd' => 'foobarbaz',
            ],
            'c' => ['a' => 'bla', 'b' => 'blubb'],
        ], 'base');

        $c = $a->baseonTop($b);

        $this->assertEquals([0, 1], $c['a']); // non associative arrays may not be merged.
        $this->assertEquals('foo', $c['b']['a']);
        $this->assertEquals('bar', $c['b']['b']);
        $this->assertEquals('foobar', $c['b']['c']);
        $this->assertEquals('foobarbaz', $c['b']['d']);
        $this->assertEquals('1', $c['c']['1']);
    }

    public function testExpandCommands(): void
    {
        $replacements = [
            '%one.two%' => 'Example 1',
            '%one.three%' => 'Example 2',
        ];

        $commands = [
            'First %one.two% example',
            'Second %one.two% %one.two% example',
            'This %one.two% %one.three% example',
        ];
        $result = Utilities::expandStrings($commands, $replacements);

        $this->assertEquals([
            'First Example 1 example',
            'Second Example 1 Example 1 example',
            'This Example 1 Example 2 example',
        ], $result);
    }

    public function testExpandVariables(): void
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
                    'three' => 'Three',
                ],
            ],
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

    public function testExtractCallback(): void
    {
        [$callback, $args] = Utilities::extractCallback('execute(docker, run)');
        $this->assertEquals('execute', $callback);
        $this->assertEquals(['docker', 'run'], $args);

        [$callback, $args] = Utilities::extractCallback('execute(deploy)');
        $this->assertEquals('execute', $callback);
        $this->assertEquals(['deploy'], $args);

        $result = Utilities::extractCallback('something is going on');
        $this->assertFalse($result);
    }

    /**
     * @throws ArgumentParsingException
     */
    public function testExtractArguments(): void
    {
        $this->assertEquals(['hello world'], Utilities::extractArguments('hello world'));
        $this->assertEquals(['hello world'], Utilities::extractArguments('"hello world"'));
        $this->assertEquals(['hello world', '10'], Utilities::extractArguments('"hello world", 10'));
        $this->assertEquals(['10', 'hello world', '20'], Utilities::extractArguments('10, "hello world", 20'));
        $this->assertEquals(['10', 'hello, world', '20'], Utilities::extractArguments('10, "hello, world", 20'));
        $this->assertEquals(
            [1, '  hello, world  ', 'foo bar'],
            Utilities::extractArguments('1, "  hello, world  ", foo bar')
        );
    }

    public function testExtractInvalidArguments(): void
    {
        $this->expectException(ArgumentParsingException::class);
        $this->assertEquals(['hello world', '10'], Utilities::extractArguments('"hello world, 10'));
    }

    public function testExtractInvalidArguments2(): void
    {
        $this->expectException(ArgumentParsingException::class);
        $this->assertEquals(['hello world', '10'], Utilities::extractArguments('"hello world", "10'));
    }

    public function testSlugify(): void
    {
        $this->assertEquals('asentencewithoutwords', Utilities::slugify('A sentence without Words'));
        $this->assertEquals('a-sentence-without-words', Utilities::slugify('A sentence without Words', '-'));
    }

    public function testGetNextStableVersion(): void
    {
        $this->assertEquals('3.6.1', Utilities::getNextStableVersion('3.6.1'));
        $this->assertEquals('3.6', Utilities::getNextStableVersion('3.6'));
        $this->assertEquals('3.6.0', Utilities::getNextStableVersion('3.6.0-beta.1'));
        $this->assertEquals('3.6.10', Utilities::getNextStableVersion('3.6.10-beta.5'));
        $this->assertEquals('3.7.10', Utilities::getNextStableVersion('3.7.10-alpha.5'));
    }

    public function testCleanupString(): void
    {
        $mappings = [
            '1.0' => '1.0',
            'whatever it takes/foo bar' => 'whatever-it-takes-foo-bar',
            '[äöü]' => '-äöü',
        ];

        foreach ($mappings as $input => $result) {
            $this->assertEquals($result, Utilities::cleanupString($input));
        }
    }

    public function testArgumentsParsing(): void
    {
        $args = Utilities::parseArguments('password=aFQd=BDq_ys9j72frDgM');
        $this->assertEquals('aFQd=BDq_ys9j72frDgM', $args['password']);
    }

    /**
     * @throws \Exception
     */
    public function testRelativePharUrls(): void
    {
        $url = 'phar:///usr/local/bin/phab/config/scaffold/mbb/./mbb-base.yml';
        $this->assertEquals(
            'phar:///usr/local/bin/phab/config/scaffold/mbb/mbb-base.yml',
            Utilities::resolveRelativePaths($url)
        );
    }

    /**
     * @throws \Exception
     */
    public function testRelativeFileUrls(): void
    {
        $url = 'file:///usr/local/bin/phab/config/scaffold/mbb/./mbb-base.yml';
        $this->assertEquals(
            'file:///usr/local/bin/phab/config/scaffold/mbb/mbb-base.yml',
            Utilities::resolveRelativePaths($url)
        );
    }
}
