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
use MakiseCo\Postgres\Driver\Swoole\SwooleConnection;
use MakiseCo\Postgres\Driver\Swoole\SwooleConnector;
use MakiseCo\Postgres\PostgresPool;
use Swoole\Coroutine\PostgreSQL;

/**
 * @requires extension swoole_postgresql
 */
class SwoolePoolTest extends AbstractConnectionTest
{
    private const POOL_SIZE = 3;

    /** @var PostgreSQL[] */
    protected array $handles = [];

    public function createLink(string $connectionString): Link
    {
        for ($i = 0; $i < self::POOL_SIZE; ++$i) {
            $this->handles[] = $handle = new PostgreSQL();
            $handle->connect($connectionString);
        }

        $connector = $this->createMock(SwooleConnector::class);
        $connector->method('connect')
            ->willReturnCallback(
                function (): SwooleConnection {
                    static $count = 0;

                    if (!isset($this->handles[$count])) {
                        $this->fail("createConnection called too many times");
                    }

                    $handle = $this->handles[$count];
                    ++$count;

                    return new SwooleConnection($handle);
                }
            );

        $config = ConnectionConfigProvider::getConfig();

        $pool = new PostgresPool($config, $connector);
        $pool->setMaxActive(self::POOL_SIZE);
        $pool->setMinActive(0);
        $pool->init();

        $handle = \reset($this->handles);

        $handle->query("DROP TABLE IF EXISTS test");

        $result = $handle->query("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        if (!$result) {
            self::fail('Could not create test table.');
        }

        $handle->prepare('test_stmt', "INSERT INTO test VALUES (\$1, \$2)");

        foreach ($this->getData() as $row) {
            $result = $handle->execute('test_stmt', $row);

            if (!$result) {
                self::fail('Could not insert test data.');
            }
        }

        return $pool;
    }

    /**
     * @doesNotPerformAssertions Listen is not supported by Swoole driver
     */
    public function testListen(): void
    {
    }

    /**
     * @doesNotPerformAssertions Listen is not supported by Swoole driver
     */
    public function testListenInterruptedByClosedConnection(): void
    {
    }

    /**
     * @doesNotPerformAssertions Listen is not supported by Swoole driver
     */
    public function testListenOnSameChannel(): void
    {
    }

    /**
     * @doesNotPerformAssertions Listen is not supported by Swoole driver
     */
    public function testNotify(): void
    {
    }

    protected function tearDown(): void
    {
        $this->handles[0]->query('ROLLBACK');
        $this->handles[0]->query("DROP TABLE test");

        $this->handles = [];

        $this->connection->close();
    }
}
