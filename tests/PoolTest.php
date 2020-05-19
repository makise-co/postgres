<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\ConnectionConfig;
use MakiseCo\Postgres\ConnectionPool;
use MakiseCo\Postgres\Exception\TransactionError;
use MakiseCo\Postgres\PoolConfig;
use MakiseCo\Postgres\ResultSet;
use MakiseCo\Postgres\Transaction;
use Swoole\Coroutine;
use Swoole\Timer;
use Throwable;

class PoolTest extends CoroTestCase
{
    use PostgresTrait;

    private ConnectionPool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pool = $this->getPool();
    }

    protected function tearDown(): void
    {
        $this->pool->close();

        parent::tearDown();
    }

    protected function getPoolConfig(int $minActive = 2, int $maxActive = 2): PoolConfig
    {
        return new PoolConfig($minActive, $maxActive);
    }

    protected function getPool(?array $poolConfig = null, ?ConnectionConfig $connectionConfig = null): ConnectionPool
    {
        if (null === $poolConfig) {
            $poolConfig = $this->getPoolConfig();
        }

        if (null === $connectionConfig) {
            $connectionConfig = $this->getConnectConfig();
        }

        $pool = new ConnectionPool($poolConfig, $connectionConfig, null);
        $pool->init();

        $pool->query("DROP TABLE IF EXISTS test");
        $pool->query("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        $stmt = $pool->prepare("INSERT INTO test VALUES (\$1, \$2)");

        foreach ($this->getData() as $row) {
            $stmt->execute($row);
        }

        unset($stmt);

        return $pool;
    }

    public function testQueryWithTupleResult(): void
    {
        $result = $this->pool->query("SELECT * FROM test");

        $this->assertSame(1, $this->pool->getIdleCount());

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        $data = $this->getData();

        $i = 0;
        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[$i][0], $row['domain']);
            $this->assertSame($data[$i][1], $row['tld']);
            $i++;
        }

        $this->assertSame(count($data), $i);

        $this->assertSame(2, $this->pool->getIdleCount());
    }

    public function testPrepare(): void
    {
        $query = "SELECT * FROM test WHERE domain=\$1";

        $statement = $this->pool->prepare($query);

        $this->assertSame(1, $this->pool->getIdleCount());

        $this->assertSame($query, $statement->getQuery());

        $data = $this->getData()[0];

        $result = $statement->execute([$data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }

        unset($statement); // force statement object destruction

        $this->assertSame(2, $this->pool->getIdleCount());
    }

    public function testTransaction(): void
    {
        $isolation = Transaction::ISOLATION_COMMITTED;

        $transaction = $this->pool->beginTransaction($isolation);

        $this->assertSame(1, $this->pool->getIdleCount());

        $data = $this->getData()[0];

//        $this->assertTrue($transaction->isAlive());
        $this->assertTrue($transaction->isActive());
        $this->assertSame($isolation, $transaction->getIsolationLevel());

        $transaction->createSavepoint('test');

        $statement = $transaction->prepare("SELECT * FROM test WHERE domain=:domain");
        $result = $statement->execute(['domain' => $data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $result = $transaction->execute("SELECT * FROM test WHERE domain=\$1 FOR UPDATE", [$data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $transaction->rollbackTo('test');

        $transaction->commit();

        unset($statement); // force destruction of statement

        $this->assertSame(2, $this->pool->getIdleCount());

//        $this->assertFalse($transaction->isAlive());
        $this->assertFalse($transaction->isActive());

        try {
            $result = $transaction->execute("SELECT * FROM test");
            $this->fail('Query should fail after transaction commit');
        } catch (TransactionError $exception) {
            // Exception expected.
        }
    }

    public function testListen(): void
    {
        $channel = "test";

        Timer::after(
            100,
            function () use ($channel) {
                try {
                    $this->pool->query(sprintf("NOTIFY %s, '%s'", $channel, '0'));
                    $this->pool->query(sprintf("NOTIFY %s, '%s'", $channel, '1'));
                } catch (Throwable $e) {
                    $this->fail("Query error: {$e->getMessage()}");
                }
            }
        );

        $chan = new Coroutine\Channel(1);

        $listener = $this->pool->listen($channel, null);

        Timer::after(
            300,
            function () use ($channel, $chan, $listener) {
                try {
                    $listener->unlisten($channel);
                } catch (Throwable $e) {
                    $this->fail("Unlisten error: {$e->getMessage()}");
                } finally {
                    $chan->push(1, 0.001);
                }
            }
        );

        $count = 0;
        while ($notification = $listener->getNotification()) {
            $this->assertSame($channel, $notification->channel);
            $this->assertSame((string)$count, $notification->payload);
            $count++;
        }

        $chan->pop(2);

        $this->assertSame(2, $count);
        $this->assertSame(2, $this->pool->getIdleCount());
    }
}
