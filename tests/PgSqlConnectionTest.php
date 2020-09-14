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
use MakiseCo\Postgres\Driver\PgSql\PgSqlConnection;
use MakiseCo\Postgres\Driver\PgSql\PgSqlResultSet;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use Swoole\Event;
use Swoole\Timer;

/**
 * @requires extension pgsql
 */
class PgSqlConnectionTest extends AbstractConnectionTest
{
    /**
     * @var resource PostgreSQL connection handle
     */
    protected $handle;

    public function createLink(string $connectionString): Link
    {
        $this->handle = \pg_connect($connectionString, \PGSQL_CONNECT_FORCE_NEW);

        \pg_query($this->handle, "DROP TABLE IF EXISTS test");

        $result = \pg_query($this->handle, "CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        if (!$result) {
            $this->fail('Could not create test table.');
        }

        foreach ($this->getData() as $row) {
            $result = \pg_query_params($this->handle, "INSERT INTO test VALUES (\$1, \$2)", $row);

            if (!$result) {
                $this->fail('Could not insert test data.');
            }
        }

        return PgSqlConnection::connect(ConnectionConfigProvider::getConfig());
    }

    protected function tearDown(): void
    {
        if ($this->handle !== null) {
            \pg_get_result($this->handle); // Consume any leftover results from test.
            \pg_query($this->handle, "ROLLBACK");
            \pg_query($this->handle, "DROP TABLE test");
            \pg_close($this->handle);

            $this->handle = null;
        }

        try {
            $this->connection->close();
        } catch (\Throwable $e) {
        }
    }

    public function testResults(): void
    {
        \assert($this->connection instanceof PgSqlConnection);

        $result = $this->connection->query("SELECT * FROM test");
        \assert($result instanceof PgSqlResultSet);

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
                    Event::write(\pg_socket($this->handle), 'bad data');
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
