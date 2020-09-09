<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\Exception\ParseException;
use MakiseCo\Postgres\Internal\ArrayParser;
use PHPUnit\Framework\TestCase;

use function implode;

class ArrayParserTest extends TestCase
{
    private ArrayParser $parser;

    public function setUp(): void
    {
        $this->parser = new ArrayParser();
    }

    public function testSingleDimensionalArray(): void
    {
        $array = ["one", "two", "three"];
        $string = '{' . implode(',', $array) . '}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testMultiDimensionalArray(): void
    {
        $array = ["one", "two", ["three", "four"], "five"];
        $string = '{one, two, {three, four}, five}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testQuotedStrings(): void
    {
        $array = ["one", "two", ["three", "four"], "five"];
        $string = '{"one", "two", {"three", "four"}, "five"}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testAlternateDelimiter(): void
    {
        $array = ["1,2,3", "3,4,5"];
        $string = '{1,2,3;3,4,5}';

        self::assertSame($array, $this->parser->parse($string, null, ';'));
    }

    public function testEscapedQuoteDelimiter(): void
    {
        $array = ['va"lue1', 'value"2'];
        $string = '{"va\\"lue1", "value\\"2"}';

        self::assertSame($array, $this->parser->parse($string, null, ','));
    }

    public function testNullValue(): void
    {
        $array = ["one", null, "three"];
        $string = '{one, NULL, three}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testQuotedNullValue(): void
    {
        $array = ["one", "NULL", "three"];
        $string = '{one, "NULL", three}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testCast(): void
    {
        $array = [1, 2, 3];
        $string = '{' . implode(',', $array) . '}';

        $cast = static function (string $value): int {
            return (int) $value;
        };

        self::assertSame($array, $this->parser->parse($string, $cast));
    }

    public function testCastWithNull(): void
    {
        $array = [1, 2, null, 3];
        $string = '{1,2,NULL,3}';

        $cast = static function (string $value): int {
            return (int) $value;
        };

        self::assertSame($array, $this->parser->parse($string, $cast));
    }

    public function testCastWithMultidimensionalArray(): void
    {
        $array = [1, 2, [3, 4], [5], 6, 7, [[8, 9], 10]];
        $string = '{1,2,{3,4},{5},6,7,{{8,9},10}}';

        $cast = static function (string $value): int {
            return (int) $value;
        };

        self::assertSame($array, $this->parser->parse($string, $cast));
    }

    public function testRandomWhitespace(): void
    {
        $array = [1, 2, [3, 4], [5], 6, 7, [[8, 9], 10]];
        $string = " {1, 2, { 3 ,\r 4 },{ 5} \n\t ,6 , 7, { {8,\t 9}, 10} }  \n";

        $cast = static function (string $value): int {
            return (int) $value;
        };

        self::assertSame($array, $this->parser->parse($string, $cast));
    }

    public function testEscapedBackslashesInQuotedValue(): void
    {
        $array = ["test\\ing", "esca\\ped\\"];
        $string = '{"test\\\\ing", "esca\\\\ped\\\\"}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testEmptyArray(): void
    {
        $array = [];
        $string = '{}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testArrayContainingEmptyArray(): void
    {
        $array = [[], [1], []];
        $string = '{{},{1},{}}';

        $cast = static function (string $value): int {
            return (int) $value;
        };

        self::assertSame($array, $this->parser->parse($string, $cast));
    }

    public function testArrayWithEmptyString(): void
    {
        $array = [''];
        $string = '{""}';

        self::assertSame($array, $this->parser->parse($string));
    }

    public function testMalformedNestedArray(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unexpected end of data');

        $string = '{{}';
        $this->parser->parse($string);
    }

    public function testEmptyString(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unexpected end of data');

        $string = ' ';
        $this->parser->parse($string);
    }

    public function testNoOpeningBracket(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Missing opening bracket');

        $string = '"one", "two"}';
        $this->parser->parse($string);
    }

    public function testNoClosingBracket(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unexpected end of data');

        $string = '{"one", "two"';
        $this->parser->parse($string);
    }

    public function testExtraClosingBracket(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Data left in buffer after parsing');

        $string = '{"one", "two"}}';
        $this->parser->parse($string);
    }

    public function testTrailingData(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Data left in buffer after parsing');

        $string = '{"one", "two"} data}';
        $this->parser->parse($string);
    }

    public function testMissingQuote(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Could not find matching quote in quoted value');

        $string = '{"one", "two}';
        $this->parser->parse($string);
    }

    public function testInvalidDelimiter(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid delimiter');

        $string = '{"one"; "two"}';
        $this->parser->parse($string);
    }
}
