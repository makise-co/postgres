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
use MakiseCo\Postgres\Driver\Pq\PqConnection;
use MakiseCo\Postgres\Driver\Pq\PqConnector;
use MakiseCo\Postgres\PostgresPool;

/**
 * @requires extension pgsql
 */
class PgSqlPoolTest extends AbstractLinkTest
{
    private const POOL_SIZE = 3;

    /** @var \pq\Connection[] */
    protected $handles = [];

    public function createLink(string $connectionString): Link
    {
        for ($i = 0; $i < self::POOL_SIZE; ++$i) {
            $this->handles[] = $handle = new \pq\Connection($connectionString);
            $handle->nonblocking = true;
            $handle->unbuffered = true;
        }

        $connector = $this->createMock(PqConnector::class);
        $connector->method('connect')
            ->willReturnCallback(
                function (): PqConnection {
                    static $count = 0;

                    if (!isset($this->handles[$count])) {
                        $this->fail("createConnection called too many times");
                    }

                    $handle = $this->handles[$count];
                    ++$count;

                    return new PqConnection($handle);
                }
            );

        $config = ConnectionConfigProvider::getConfig();

        $pool = new PostgresPool($config, $connector);
        $pool->setMaxActive(self::POOL_SIZE);
        $pool->setMinActive(0);
        $pool->init();

        $handle = \reset($this->handles);

        $handle->exec("DROP TABLE IF EXISTS test");

        $result = $handle->exec("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        if (!$result) {
            self::fail('Could not create test table.');
        }

        foreach ($this->getData() as $row) {
            $result = $handle->execParams("INSERT INTO test VALUES (\$1, \$2)", $row);

            if (!$result) {
                self::fail('Could not insert test data.');
            }
        }

        return $pool;
    }

    protected function tearDown(): void
    {
        $this->handles[0]->exec('ROLLBACK');
        $this->handles[0]->exec("DROP TABLE test");

        $this->connection->close();
    }
}
