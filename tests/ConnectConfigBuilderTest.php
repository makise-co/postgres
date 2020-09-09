<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\ConnectionConfigBuilder;
use PHPUnit\Framework\TestCase;

class ConnectConfigBuilderTest extends TestCase
{
    private ConnectionConfigBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ConnectionConfigBuilder();
    }

    public function testWithBasicParams(): void
    {
        $config = $this
            ->builder
            ->withHost('postgres')
            ->withUser('makise')
            ->withPort(10228)
            ->withPassword('El-Psy-Congroo')
            ->withDatabase('cern')
            ->build();

        $dsn = $config->__toString();

        self::assertSame(
            "host=postgres port=10228 user='makise' password='El-Psy-Congroo' dbname='cern'",
            $dsn
        );
    }

    /**
     * @depends testWithBasicParams
     */
    public function testWithBasicParamsMergeOptions(): void
    {
        $config = $this
            ->builder
            ->withHost('postgres')
            ->withUser('makise')
            ->withPort(10228)
            ->withPassword('El-Psy-Congroo')
            ->withDatabase('cern')
            ->build();

        $dsn = $config->__toString();

        self::assertSame(
            "host=postgres port=10228 user='makise' password='El-Psy-Congroo' dbname='cern'",
            $dsn
        );
    }

    /**
     * @depends testWithBasicParams
     */
    public function testWithFullParams(): void
    {
        $config = $this
            ->builder
            ->withHost('postgres')
            ->withUser('makise')
            ->withPort(10228)
            ->withPassword('El-Psy-Congroo')
            ->withDatabase('cern')
            ->withSearchPath(['public'])
            ->withTimezone('UTC')
            ->withEncoding('utf8')
            ->withApplicationName('Makise src Client')
            ->withConnectTimeout(1)
            ->withUnbuffered(false)
            ->withOption('sslmode', 'allow')
            ->build();

        self::assertFalse($config->getUnbuffered());
        self::assertSame(1.0, $config->getConnectTimeout());

        $dsn = $config->__toString();

        self::assertSame(
            "host=postgres port=10228 user='makise' password='El-Psy-Congroo' dbname='cern' sslmode='allow'" .
            " application_name='Makise src Client' client_encoding='utf8' options='-csearch_path=public -ctimezone=UTC'",
            $dsn
        );
    }

    public function testFromArray(): void
    {
        $config = $this->builder->fromArray(
            [
                'host' => 'postgres',
                'user' => 'makise',
                'port' => 10228,
                'password' => 'El-Psy-Congroo',
                'database' => 'cern',
                'search_path' => ['public'],
                'timezone' => 'UTC',
                'encoding' => 'utf8',
                'application_name' => 'Makise src Client',
                'connect_timeout' => 1.0,
                'unbuffered' => false,
                'options' => [
                    'sslmode' => 'allow',
                ]
            ]
        )->build();

        self::assertFalse($config->getUnbuffered());
        self::assertSame(1.0, $config->getConnectTimeout());

        $dsn = $config->__toString();

        self::assertSame(
            "host=postgres port=10228 user='makise' password='El-Psy-Congroo' dbname='cern' sslmode='allow'" .
            " application_name='Makise src Client' client_encoding='utf8' options='-csearch_path=public -ctimezone=UTC'",
            $dsn
        );
    }

//    public function testFromString(): void
//    {
//        $config = $this->builder->fromString(
//            "host=postgres port=10228 user=makise password='El-Psy-Congroo'" .
//            " dbname=cern application_name='Makise src Client' client_encoding=utf8"
//        )->build();
//
//        $dsn = $config->__toString();
//
//        self::assertSame(
//            "host=postgres port=10228 user='makise' password='El-Psy-Congroo' dbname='cern'" .
//            " application_name='Makise src Client' client_encoding='utf8'",
//            $dsn
//        );
//    }
}
