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
use Swoole\Coroutine\PostgreSQL;

class SwooleConnectionTest extends AbstractConnectionTest
{
    protected PostgreSQL $handle;

    /**
     * @inheritDoc
     */
    public function createLink(string $connectionString): Link
    {
        $this->handle = new PostgreSQL();
        $this->handle->connect($connectionString);

        $this->handle->query('DROP TABLE IF EXISTS test');

        $result = $this->handle->query("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        if (!$result) {
            $this->fail('Could not create test table.');
        }

        $this->handle->prepare('test_stmt', "INSERT INTO test VALUES (\$1, \$2)");

        foreach ($this->getData() as $row) {
            $result = $this->handle->execute('test_stmt', $row);

            if (!$result) {
                $this->fail('Could not insert test data.');
            }
        }

        $this->handle->query('DEALLOCATE test_stmt');

        return SwooleConnection::connect(ConnectionConfigProvider::getConfig());
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
}
