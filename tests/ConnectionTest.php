<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use Error;
use MakiseCo\Postgres\BufferedResultSet;
use MakiseCo\Postgres\CommandResult;
use MakiseCo\Postgres\ConnectConfig;
use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\Exception\ConcurrencyException;
use MakiseCo\Postgres\Exception\ConnectionException;
use MakiseCo\Postgres\Exception\FailureException;
use MakiseCo\Postgres\Exception\QueryError;
use MakiseCo\Postgres\Exception\QueryExecutionError;
use MakiseCo\Postgres\Exception\TransactionError;
use MakiseCo\Postgres\Listener;
use MakiseCo\Postgres\Notification;
use MakiseCo\Postgres\ResultSet;
use MakiseCo\Postgres\Statement;
use MakiseCo\Postgres\Transaction;
use MakiseCo\Postgres\UnbufferedResultSet;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Swoole\Event;
use Swoole\Timer;
use Throwable;

use function sprintf;

class ConnectionTest extends CoroTestCase
{
    use PostgresTrait {
        getConnection as parentGetConnection;
    }

    protected function getConnection(?ConnectConfig $config = null): Connection
    {
        $connection = $this->parentGetConnection($config);

        $connection->query("DROP TABLE IF EXISTS test");
        $connection->query("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        $stmt = $connection->prepare("INSERT INTO test VALUES (\$1, \$2)");

        foreach ($this->getData() as $row) {
            $stmt->execute($row);
        }

        $stmt->close();

        return $connection;
    }

    public function testDisconnect(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection is closed');

        $connection = $this->getConnection();
        $connection->disconnect();

        $this->assertFalse($connection->isConnected());

        $connection->query('SELECT 1');
    }

    /**
     * @depends testDisconnect
     */
    public function testDisconnectWithStatement(): void
    {
        $this->expectException(FailureException::class);
        $this->expectExceptionMessage('Statement is closed');

        $connection = $this->getConnection();

        $stmt = $connection->prepare('SELECT ? AS n');

        $connection->disconnect();

        $this->assertFalse($connection->isConnected());

        $this->assertFalse($stmt->isAlive());
        $this->assertTrue($stmt->isClosed());

        $stmt->execute([0]);
    }

    public function testConnect(): void
    {
        $connection = $this->getConnection();
        $connection->disconnect();

        $this->assertFalse($connection->isConnected());

        $connection->connect();

        $this->assertTrue($connection->isConnected());

        $connection->query('SELECT 1');
    }

    public function testUnbufferedResultSet(): void
    {
        $connection = $this->getConnection();

        $this->assertTrue($connection->getHandle()->getPq()->unbuffered);

        $result = $connection->query('SELECT * FROM test');

        /* @var UnbufferedResultSet $result */
        $this->assertInstanceOf(UnbufferedResultSet::class, $result);

        $data = $this->getData();

        $i = 0;
        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[$i][0], $row['domain']);
            $this->assertSame($data[$i][1], $row['tld']);
            $i++;
        }

        $this->assertSame(count($data), $i);
    }

    public function testBufferedResultSet(): void
    {
        $connection = $this->getConnection(
            $this->getConnectConfig(2, false)
        );

        $this->assertFalse($connection->getHandle()->getPq()->unbuffered);

        $result = $connection->query('SELECT * FROM test');

        /* @var BufferedResultSet $result */
        $this->assertInstanceOf(BufferedResultSet::class, $result);

        $data = $this->getData();

        $i = 0;
        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[$i][0], $row['domain']);
            $this->assertSame($data[$i][1], $row['tld']);
            $i++;
        }

        $this->assertSame(count($data), $i);
    }

    public function testQueryWithTupleResult(): void
    {
        $connection = $this->getConnection();
        $result = $connection->query("SELECT * FROM test");

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
    }

    public function testQueryWithUnconsumedTupleResult(): void
    {
        $connection = $this->getConnection();

        $result = $connection->query("SELECT * FROM test");

        $this->assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $result = $connection->query("SELECT * FROM test");

        $this->assertInstanceOf(ResultSet::class, $result);

        $data = $this->getData();

        $i = 0;
        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[$i][0], $row['domain']);
            $this->assertSame($data[$i][1], $row['tld']);
            $i++;
        }

        $this->assertSame(count($data), $i);
    }

    public function testQueryWithCommandResult(): void
    {
        $connection = $this->getConnection();

        $result = $connection->query("INSERT INTO test VALUES ('canon', 'jp')");

        $this->assertInstanceOf(CommandResult::class, $result);
        $this->assertSame(1, $result->getAffectedRowCount());
    }

    public function testQueryWithEmptyQuery(): void
    {
        $this->expectException(QueryError::class);

        $connection = $this->getConnection();
        $connection->query('');
    }

    public function testQueryWithSyntaxError(): void
    {
        $connection = $this->getConnection();

        try {
            $connection->query("SELECT & FROM test");
            $this->fail(sprintf("An instance of %s was expected to be thrown", QueryExecutionError::class));
        } catch (QueryExecutionError $exception) {
            $diagnostics = $exception->getDiagnostics();
            $this->assertArrayHasKey("sqlstate", $diagnostics);
        }
    }

    public function testQueryWithTimeoutError(): void
    {
        $connection = $this->getConnection();

        try {
            $connection->query("SELECT pg_sleep(1)", 0.05);
            $this->fail(sprintf("An instance of %s was expected to be thrown", QueryExecutionError::class));
        } catch (QueryExecutionError $exception) {
            $diagnostics = $exception->getDiagnostics();
            $this->assertArrayHasKey("sqlstate", $diagnostics);
        }

        $connection->query('SELECT 1');
    }

    public function testPrepare(): void
    {
        $connection = $this->getConnection();

        $query = "SELECT * FROM test WHERE domain=\$1";

        $statement = $connection->prepare($query);

        $this->assertSame($query, $statement->getQuery());

        $data = $this->getData()[0];

        $result = $statement->execute([$data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareWithNamedParams(): void
    {
        $connection = $this->getConnection();

        $query = "SELECT * FROM test WHERE domain=:domain AND tld=:tld";
        $expectedQuery = "SELECT * FROM test WHERE domain=$1 AND tld=$2";

        $statement = $connection->prepare($query);

        $data = $this->getData()[0];

        $this->assertSame($expectedQuery, $statement->getQuery());

        $result = $statement->execute(['domain' => $data[0], 'tld' => $data[1]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareWithUnnamedParams(): void
    {
        $connection = $this->getConnection();

        $query = "SELECT * FROM test WHERE domain=? AND tld=?";
        $expectedQuery = "SELECT * FROM test WHERE domain=$1 AND tld=$2";

        $statement = $connection->prepare($query);

        $data = $this->getData()[0];

        $this->assertSame($expectedQuery, $statement->getQuery());

        $result = $statement->execute([$data[0], $data[1]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareWithNamedParamsWithDataAppearingAsNamedParam(): void
    {
        $connection = $this->getConnection();

        $query = "SELECT * FROM test WHERE domain=:domain OR domain=':domain'";
        $expectedQuery = "SELECT * FROM test WHERE domain=$1 OR domain=':domain'";

        $statement = $connection->prepare($query);

        $data = $this->getData()[0];

        $this->assertSame($expectedQuery, $statement->getQuery());

        $result = $statement->execute(['domain' => $data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareInvalidQuery(): void
    {
        $this->expectException(QueryExecutionError::class);
        $this->expectExceptionMessage('column "invalid" does not exist');

        $query = "SELECT * FROM test WHERE invalid=\$1";

        $connection = $this->getConnection();

        $connection->prepare($query);
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareSameQuery(): void
    {
        $connection = $this->getConnection();

        $sql = "SELECT * FROM test WHERE domain=\$1";

        $statement1 = $connection->prepare($sql);
        $statement2 = $connection->prepare($sql);

        unset($statement1);

        $data = $this->getData()[0];

        $result = $statement2->execute([$data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }
    }

    public function testPrepareSimilarQueryReturnsDifferentStatements(): void
    {
        $connection = $this->getConnection();

        $this->expectException(ConcurrencyException::class);

        $wg = new WaitGroup();
        $wg->add(2);

        $ex = null;
        $statement1 = null;
        $statement2 = null;
        Coroutine::create(
            function () use (&$statement1, $wg, $connection, &$ex) {
                try {
                    $statement1 = $connection->prepare("SELECT * FROM test WHERE domain=\$1");
                } catch (Throwable $e) {
                    $ex = $e;
                }
                $wg->done();
            }
        );
        Coroutine::create(
            function () use (&$statement2, $wg, $connection, &$ex) {
                try {
                    $statement2 = $connection->prepare("SELECT * FROM test WHERE domain=:domain");
                } catch (Throwable $e) {
                    $ex = $e;
                }

                $wg->done();
            }
        );
        $wg->wait();

        if ($ex) {
            throw $ex;
        }

        /* @var Statement $statement1 */
        $this->assertInstanceOf(Statement::class, $statement1);
        /* @var Statement $statement2 */
        $this->assertInstanceOf(Statement::class, $statement2);

        $this->assertNotSame($statement1, $statement2);

        $data = $this->getData()[0];

        $results = [];

        $res1 = $statement1->execute([$data[0]]);
        $results1 = [];
        while ($row = $res1->fetchAssoc()) {
            $results1[] = $row;
        }

        $res2 = $statement2->execute(['domain' => $data[0]]);
        $results2 = [];
        while ($row = $res2->fetchAssoc()) {
            $results2[] = $row;
        }

        $results[] = $results1;
        $results[] = $results2;

        foreach ($results as $result) {
            foreach ($result as $row) {
                $this->assertSame($data[0], $row['domain']);
                $this->assertSame($data[1], $row['tld']);
            }
        }
    }

    public function testPrepareThenExecuteWithUnconsumedTupleResult(): void
    {
        $connection = $this->getConnection();

        $statement = $connection->prepare("SELECT * FROM test");

        /** @var ResultSet $result */
        $result = $statement->execute();

        $this->assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        /** @var ResultSet $result $result */
        $result = $statement->execute();

        $this->assertInstanceOf(ResultSet::class, $result);

        $data = $this->getData();

        $i = 0;
        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[$i][0], $row['domain']);
            $this->assertSame($data[$i][1], $row['tld']);

            $i++;
        }
    }

    public function testExecute(): void
    {
        $data = $this->getData()[0];

        $connection = $this->getConnection();

        /** @var ResultSet $result */
        $result = $connection->execute("SELECT * FROM test WHERE domain=\$1", [$data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testExecute
     */
    public function testExecuteWithNamedParams(): void
    {
        $data = $this->getData()[0];

        $connection = $this->getConnection();

        /** @var ResultSet $result */
        $result = $connection->execute(
            "SELECT * FROM test WHERE domain=:domain",
            ['domain' => $data[0]]
        );

        $this->assertInstanceOf(ResultSet::class, $result);

        $this->assertSame(2, $result->getFieldCount());

        while ($row = $result->fetchAssoc()) {
            $this->assertSame($data[0], $row['domain']);
            $this->assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testExecute
     */
    public function testExecuteWithInvalidParams(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Value for unnamed parameter at position 0 missing");

        $connection = $this->getConnection();
        $connection->execute("SELECT * FROM test WHERE domain=\$1");
    }

    /**
     * @depends testExecute
     */
    public function testExecuteWithInvalidNamedParams(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Value for named parameter 'domain' missing");

        $connection = $this->getConnection();

        $connection->execute("SELECT * FROM test WHERE domain=:domain", ['tld' => 'com']);
    }

    /**
     * @depends testQueryWithTupleResult
     */
    public function testSimultaneousQueryWithDelay(): void
    {
        $this->expectException(ConcurrencyException::class);

        $connection = $this->getConnection();

        $ex = null;

        $wg = new WaitGroup();
        $wg->add(2);

        $callback = function ($value) use ($connection, $wg, &$ex) {
            try {
                $result = $connection->query("SELECT {$value} as value");

                if ($value) {
                    Coroutine::sleep(0.1);
                }

                $row = $result->fetchAssoc();
                $this->assertEquals($value, $row['value']);
            } catch (Throwable $e) {
                $ex = $e;
            }

            $wg->done();
        };

        Coroutine::create($callback, 0);
        Coroutine::create($callback, 1);

        $wg->wait();

        if ($ex) {
            throw $ex;
        }
    }

    public function testTransaction(): void
    {
        $connection = $this->getConnection();

        $isolation = Transaction::ISOLATION_COMMITTED;

        $transaction = $connection->beginTransaction($isolation);

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
        $count = 0;

        $connection = $this->getConnection();
        $connection->listen(
            $channel,
            function (Notification $notification) use ($channel, &$count) {
                $this->assertSame($channel, $notification->channel);
                $this->assertSame((string)$count, $notification->payload);
                $count++;
            }
        );

        Timer::after(
            100,
            function () use ($channel, $connection) {
                try {
                    $connection->query(sprintf("NOTIFY %s, '%s'", $channel, '0'));
                    $connection->query(sprintf("NOTIFY %s, '%s'", $channel, '1'));
                } catch (Throwable $e) {
                    $this->fail("Query error: {$e->getMessage()}");
                }
            }
        );

        $chan = new Coroutine\Channel(1);

        Timer::after(
            300,
            function () use ($channel, $connection, $chan) {
                try {
                    $connection->unlisten($channel);
                } catch (Throwable $e) {
                    $this->fail("Unlisten error: {$e->getMessage()}");
                } finally {
                    $chan->push(1, 0.001);
                }
            }
        );

        $chan->pop(2);

        $this->assertSame(2, $count);
    }

    public function testListenWithoutCallable(): void
    {
        $channel = "test";
        $count = 0;

        $connection = $this->getConnection();
        $listener = $connection->listen($channel, null);

        $this->assertInstanceOf(Listener::class, $listener);

        Coroutine::create(
            function (Listener $listener) use (&$count, $channel) {
                while ($notification = $listener->getNotification()) {
                    $this->assertSame($channel, $notification->channel);
                    $this->assertSame((string)$count, $notification->payload);
                    $count++;
                }
            },
            $listener
        );

        Timer::after(
            100,
            function () use ($channel, $connection) {
                try {
                    $connection->query(sprintf("NOTIFY %s, '%s'", $channel, '0'));
                    $connection->query(sprintf("NOTIFY %s, '%s'", $channel, '1'));
                } catch (Throwable $e) {
                    $this->fail("Query error: {$e->getMessage()}");
                }
            }
        );

        $chan = new Coroutine\Channel(1);

        Timer::after(
            300,
            function () use ($channel, $connection, $chan) {
                try {
                    $connection->unlisten($channel);
                } catch (Throwable $e) {
                    $this->fail("Unlisten error: {$e->getMessage()}");
                } finally {
                    $chan->push(1, 0.001);
                }
            }
        );

        $chan->pop(2);

        $this->assertSame(2, $count);
    }

    public function testListenWithoutCallableAndConnectionClosed(): void
    {
        $this->expectException(ConnectionException::class);

        $channel = "test";
        $count = 0;

        $connection = $this->getConnection();
        $listener = $connection->listen($channel, null);

        $this->assertInstanceOf(Listener::class, $listener);

        $chan = new Coroutine\Channel(1);
        $ex = null;
        // connection object is passed to prevent it from destruction
        Coroutine::create(
            function (Listener $listener, Connection $connection) use (&$count, $channel, &$ex, $chan) {
                try {
                    while ($notification = $listener->getNotification()) {
                        $this->assertSame($channel, $notification->channel);
                        $this->assertSame((string)$count, $notification->payload);
                        $count++;
                    }
                } catch (ConnectionException $e) {
                    $ex = $e;
                }
            },
            $listener,
            $connection
        );

        Timer::after(
            100,
            function () use ($connection) {
                try {
                    $connection->query('SELECT pg_terminate_backend(pg_backend_pid())');
                } catch (Throwable $e) {
                    $this->fail("Query error: {$e->getMessage()}");
                }
            }
        );

        $chan->pop(0.5);

        // work-around swoole event loop get stuck, because it can't remove event from broken descriptor
        Event::exit();
        if ($ex) {
            throw $ex;
        }
    }

    /**
     * @depends testListen
     */
    public function testNotify(): void
    {
        $channel = "test";
        $count = 0;

        $connection = $this->getConnection();
        $connection->listen(
            $channel,
            function (Notification $notification) use ($channel, &$count) {
                $this->assertSame($channel, $notification->channel);
                $this->assertSame((string)$count, $notification->payload);
                $count++;
            }
        );

        Timer::after(
            100,
            function () use ($channel, $connection) {
                try {
                    $connection->notify($channel, '0');
                    $connection->notify($channel, '1');
                } catch (Throwable $e) {
                    $this->fail("Query error: {$e->getMessage()}");
                }
            }
        );

        $chan = new Coroutine\Channel(1);

        Timer::after(
            300,
            function () use ($channel, $connection, $chan) {
                try {
                    $connection->unlisten($channel);
                } catch (Throwable $e) {
                    $this->fail("Unlisten error: {$e->getMessage()}");
                } finally {
                    $chan->push(1, 0.001);
                }
            }
        );

        $chan->pop(0.5);

        $this->assertSame(2, $count);
    }

    /**
     * @depends testListen
     */
    public function testListenOnSameChannel(): void
    {
        $channel = "test";

        $this->expectException(FailureException::class);
        $this->expectExceptionMessage("Listener on {$channel} already exists");

        $connection = $this->getConnection();
        $connection->listen(
            $channel,
            function () {
            }
        );
        $connection->listen(
            $channel,
            function () {
            }
        );
    }

    /**
     * @return array Start test data for database.
     */
    public function getData(): array
    {
        return [
            ['amphp', 'org'],
            ['github', 'com'],
            ['google', 'com'],
            ['php', 'net'],
        ];
    }
}