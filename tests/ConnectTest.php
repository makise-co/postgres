<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\Exception\ConnectionException;
use MakiseCo\Postgres\PqConnector;

use pq\Connection;

use function sprintf;

class ConnectTest extends CoroTestCase
{
    use PostgresTrait;

    public function testConnect(): void
    {
        $connector = new PqConnector($this->getConnectConfig());
        $connection = $connector->connect();

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testConnectTimeout(): void
    {
        $timeout = 0.001;

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage(sprintf("Connection timeout. Timeout: %f secs", $timeout));

        $connector = new PqConnector($this->getConnectConfig($timeout));
        $connector->connect();
    }

    /**
     * @depends testConnect
     */
    public function testConnectInvalidUser(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('FATAL:  password authentication failed for user "invalid"');

        $connector = new PqConnector($this->getConnectConfig(2, true, 'invalid'));
        $connector->connect();
    }
}