<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfig;

abstract class AbstractConnectTest extends CoroTestCase
{
    /**
     * @param ConnectionConfig $connectionConfig
     *
     * @return Connection
     */
    abstract public function connect(ConnectionConfig $connectionConfig): Connection;

    public function testConnect(): void
    {
        $config = ConnectionConfigProvider::getConfig();

        $connection = $this->connect($config);

        try {
            self::assertInstanceOf(Connection::class, $connection);
        } finally {
            $connection->close();
        }
    }

//    /**
//     * @depends testConnect
//     */
//    public function testConnectCancellationBeforeConnect(): void
//    {
//        $this->expectException(CancelledException::class);
//
//        $source = new CancellationTokenSource;
//        $token = $source->getToken();
//        $source->cancel();
//        return $this->connect(PostgresConnectionConfig::fromString('host=localhost user=postgres'), $token);
//    }

//    /**
//     * @depends testConnectCancellationBeforeConnect
//     */
//    public function testConnectCancellationAfterConnect(): \Generator
//    {
//        $source = new CancellationTokenSource;
//        $token = $source->getToken();
//        $connection = yield $this->connect(
//            PostgresConnectionConfig::fromString('host=localhost user=postgres'),
//            $token
//        );
//        $this->assertInstanceOf(Connection::class, $connection);
//        $source->cancel();
//    }

//    /**
//     * @depends testConnectCancellationBeforeConnect
//     */
//    public function testConnectInvalidUser(): Promise
//    {
//        $this->expectException(FailureException::class);
//
//        return $this->connect(
//            PostgresConnectionConfig::fromString('host=localhost user=invalid'),
//            new TimeoutCancellationToken(100)
//        );
//    }
}
