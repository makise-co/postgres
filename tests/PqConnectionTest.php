<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\Contracts\Link;
use MakiseCo\Postgres\Driver\Pq\PqBufferedResultSet;
use MakiseCo\Postgres\Driver\Pq\PqConnection;
use MakiseCo\Postgres\Driver\Pq\PqUnbufferedResultSet;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use Swoole\Event;
use Swoole\Timer;

/**
 * @requires extension pq
 */
class PqConnectionTest extends AbstractConnectionTest
{
    protected ?\pq\Connection $handle = null;

    public function createLink(string $connectionString): Link
    {
        $this->handle = new \pq\Connection($connectionString);
        $this->handle->nonblocking = true;
        $this->handle->unbuffered = true;

        $this->handle->exec("DROP TABLE IF EXISTS test");

        $result = $this->handle->exec("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        if (!$result) {
            $this->fail('Could not create test table.');
        }

        foreach ($this->getData() as $row) {
            $result = $this->handle->execParams("INSERT INTO test VALUES (\$1, \$2)", $row);

            if (!$result) {
                $this->fail('Could not insert test data.');
            }
        }

        return new PqConnection($this->handle);
    }

    protected function tearDown(): void
    {
        if ($this->handle !== null) {
            $this->handle->exec("ROLLBACK");
            $this->handle->exec("DROP TABLE test");
            $this->handle = null;
        }

        try {
            $this->connection->close();
        } catch (\Throwable $e) {
        }
    }

    public function testBufferedResults(): void
    {
        \assert($this->connection instanceof PqConnection);
        $this->connection->shouldBufferResults();

        $this->assertTrue($this->connection->isBufferingResults());

        $result = $this->connection->query("SELECT * FROM test");
        \assert($result instanceof PqBufferedResultSet);

        $this->assertSame(2, $result->getFieldCount());

        $data = $this->getData();

        for ($i = 0; $row = $result->fetch(); ++$i) {
            $this->assertSame($data[$i][0], $row['domain']);
            $this->assertSame($data[$i][1], $row['tld']);
        }
    }

    /**
     * @depends testBufferedResults
     */
    public function testUnbufferedResults(): void
    {
        \assert($this->connection instanceof PqConnection);
        $this->connection->shouldNotBufferResults();

        $this->assertFalse($this->connection->isBufferingResults());

        $result = $this->connection->query("SELECT * FROM test");
        \assert($result instanceof PqUnbufferedResultSet);

        $this->assertSame(2, $result->getFieldCount());

        $data = $this->getData();

        for ($i = 0; $row = $result->fetch(); ++$i) {
            $this->assertSame($data[$i][0], $row['domain']);
            $this->assertSame($data[$i][1], $row['tld']);
        }
    }

//    /**
//     * @depends testListen
//     */
    public function testListenInterruptedByBrokenConnection(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('server closed the connection unexpectedly');

        $channel = "test";
        $listener = $this->connection->listen($channel);

        // broking connection after 100ms
        Timer::after(100, function () {
            // dirty trick to access private properties
            $func = function () {
                /** @var self Connection */
                $pqHandle = $this->handle;

                $func = function () {
                    /** @var self PqHandle */
                    // writing bad data to socket to make src close connection
                    Event::write($this->handle->socket, 'bad data');
                };

                $func->call($pqHandle);
            };

            $func->call($this->connection);
        });

        try {
            while ($notification = $listener->getNotification()) {
            }
        } catch (\Throwable $e) {
            $this->handle = null;

            throw $e;
        }
    }
}
