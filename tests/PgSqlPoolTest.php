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
use MakiseCo\Postgres\Driver\PgSql\PgSqlConnector;
use MakiseCo\Postgres\PostgresPool;

/**
 * @requires extension pgsql
 */
class PgSqlPoolTest extends AbstractLinkTest
{
    private const POOL_SIZE = 3;

    /** @var resource[] */
    protected array $handles = [];

    public function createLink(string $connectionString): Link
    {
        for ($i = 0; $i < self::POOL_SIZE; ++$i) {
            $this->handles[] = $handle = \pg_connect(
                $connectionString,
                \PGSQL_CONNECT_FORCE_NEW
            );
        }

        $connector = $this->createMock(PgSqlConnector::class);
        $connector->method('connect')
            ->willReturnCallback(
                function (): PgSqlConnection {
                    static $count = 0;

                    if (!isset($this->handles[$count])) {
                        $this->fail("createConnection called too many times");
                    }

                    $handle = $this->handles[$count];
                    ++$count;

                    return new PgSqlConnection($handle, \pg_socket($handle));
                }
            );

        $config = ConnectionConfigProvider::getConfig();

        $pool = new PostgresPool($config, $connector);
        $pool->setMaxActive(self::POOL_SIZE);
        $pool->setMinActive(0);
        $pool->init();

        $handle = \reset($this->handles);

        \pg_query($handle, "DROP TABLE IF EXISTS test");

        $result = \pg_query("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        if (!$result) {
            self::fail('Could not create test table.');
        }

        foreach ($this->getData() as $row) {
            $result = \pg_query_params($handle, "INSERT INTO test VALUES (\$1, \$2)", $row);

            if (!$result) {
                self::fail('Could not insert test data.');
            }
        }

        return $pool;
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->handles[0])) {
            \pg_query($this->handles[0], 'ROLLBACK');
            \pg_query($this->handles[0], 'DROP TABLE test');
        }

        $this->handles = [];

        $this->connection->close();
    }
}
