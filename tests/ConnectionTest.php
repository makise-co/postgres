<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\CommandResult;
use MakiseCo\Postgres\ConnectConfig;
use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\Exception\QueryError;
use MakiseCo\Postgres\Exception\QueryExecutionError;
use MakiseCo\Postgres\ResultSet;
use MakiseCo\Postgres\Statement;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

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

    public function testUnbufferedResultSet(): void
    {
        $connection = $this->getConnection();

        $this->assertTrue($connection->getHandle()->getPq()->unbuffered);

        $result = $connection->query('SELECT * FROM test');

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
            $this->fail(\sprintf("An instance of %s was expected to be thrown", QueryExecutionError::class));
        } catch (QueryExecutionError $exception) {
            $diagnostics = $exception->getDiagnostics();
            $this->assertArrayHasKey("sqlstate", $diagnostics);
        }
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

        $this->assertInstanceOf(Statement::class, $statement1);
        $this->assertInstanceOf(Statement::class, $statement2);

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

//    /**
//     * @depends testPrepareSameQuery
//     */
//    public function testSimultaneousPrepareSameQuery(): void
//    {
//        // TODO: Fix this test because it fails with statement already exist error
//        $connection = $this->getConnection();
//
//        $sql = "SELECT * FROM test WHERE domain=\$1";
//
//        $wg = new WaitGroup();
//        $wg->add(2);
//
//        $statement1 = null;
//        $statement2 = null;
//        Coroutine::create(function () use (&$statement1, $wg, $connection, $sql) {
//            $statement1 = $connection->prepare($sql);
//            $wg->done();
//        });
//        Coroutine::create(function () use (&$statement2, $wg, $connection, $sql) {
//            $statement2 = $connection->prepare($sql);
//            $wg->done();
//        });
//        $wg->wait();
//
//        $data = $this->getData()[0];
//
//        $result = $statement1->execute([$data[0]]);
//
//        $this->assertInstanceOf(ResultSet::class, $result);
//
//        $this->assertSame(2, $result->getFieldCount());
//
//        while ($row = $result->fetchAssoc()) {
//            $this->assertSame($data[0], $row['domain']);
//            $this->assertSame($data[1], $row['tld']);
//        }
//
//        unset($statement1);
//
//        $result = $statement2->execute([$data[0]]);
//
//        $this->assertInstanceOf(ResultSet::class, $result);
//
//        $this->assertSame(2, $result->getFieldCount());
//
//        while ($row = $result->fetchAssoc()) {
//            $this->assertSame($data[0], $row['domain']);
//            $this->assertSame($data[1], $row['tld']);
//        }
//    }

    public function testPrepareSimilarQueryReturnsDifferentStatements(): void
    {
        $connection = $this->getConnection();

        $wg = new WaitGroup();
        $wg->add(2);

        $statement1 = null;
        $statement2 = null;
        Coroutine::create(function () use (&$statement1, $wg, $connection) {
            $statement1 = $connection->prepare("SELECT * FROM test WHERE domain=\$1");
            $wg->done();
        });
        Coroutine::create(function () use (&$statement2, $wg, $connection) {
            $statement2 = $connection->prepare("SELECT * FROM test WHERE domain=:domain");
            $wg->done();
        });
        $wg->wait();

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

//    public function testExecute(): void
//    {
//        // TODO: Write execute method
//        $data = $this->getData()[0];
//
//        /** @var \Amp\Postgres\ResultSet $result */
//        $result = yield $this->connection->execute("SELECT * FROM test WHERE domain=\$1", [$data[0]]);
//
//        $this->assertInstanceOf(ResultSet::class, $result);
//
//        $this->assertSame(2, $result->getFieldCount());
//
//        while (yield $result->advance()) {
//            $row = $result->getCurrent();
//            $this->assertSame($data[0], $row['domain']);
//            $this->assertSame($data[1], $row['tld']);
//        }
//    }

//    /**
//     * @depends testExecute
//     */
//    public function testExecuteWithNamedParams(): \Generator
//    {
//        // TODO: Write execute method
//        $data = $this->getData()[0];
//
//        /** @var \Amp\Postgres\ResultSet $result */
//        $result = yield $this->connection->execute(
//            "SELECT * FROM test WHERE domain=:domain",
//            ['domain' => $data[0]]
//        );
//
//        $this->assertInstanceOf(ResultSet::class, $result);
//
//        $this->assertSame(2, $result->getFieldCount());
//
//        while (yield $result->advance()) {
//            $row = $result->getCurrent();
//            $this->assertSame($data[0], $row['domain']);
//            $this->assertSame($data[1], $row['tld']);
//        }
//    }

//    /**
//     * @depends testExecute
//     */
//    public function testExecuteWithInvalidParams(): Promise
//    {
//        // TODO: Write execute method
//        $this->expectException(\Error::class);
//        $this->expectExceptionMessage("Value for unnamed parameter at position 0 missing");
//
//        return $this->connection->execute("SELECT * FROM test WHERE domain=\$1");
//    }

//    /**
//     * @depends testExecute
//     */
//    public function testExecuteWithInvalidNamedParams(): Promise
//    {
//        // TODO: Write execute method
//
//        $this->expectException(\Error::class);
//        $this->expectExceptionMessage("Value for named parameter 'domain' missing");
//
//        return $this->connection->execute("SELECT * FROM test WHERE domain=:domain", ['tld' => 'com']);
//    }

    /**
     * @depends testQueryWithTupleResult
     */
    public function testSimultaneousQuery(): void
    {
        $connection = $this->getConnection();

        $wg = new WaitGroup();
        $wg->add(2);

        $callback = function ($value) use ($connection, $wg) {
            $result = $connection->query("SELECT {$value} as value");

            if ($value) {
                Coroutine::sleep(0.1);
            }

            $row = $result->fetchAssoc();
            $this->assertEquals($value, $row['value']);

            $wg->done();
        };

        Coroutine::create($callback, 0);
        Coroutine::create($callback, 1);

        $wg->wait();
    }

//    /**
//     * @depends testSimultaneousQuery
//     */
//    public function testSimultaneousQueryWithOneFailing(): void
//    {
//        // TODO: Fix this test, because for now the parallel queries does not supported
//        $connection = $this->getConnection();
//
//        $successfulCh = new Channel(1);
//        $failingCh = new Channel(1);
//
//        // do not let coroutine to die
//        Coroutine::set([
//            'exit_condition' => function () use ($successfulCh, $failingCh) {
//                return $successfulCh->stats()['consumer_num'] === 0 && $failingCh->stats()['consumer_num'] === 0;
//            }
//        ]);
//
//        $callback = function (string $query, Channel $ch) use ($connection) {
//            try {
//                $result = $connection->query($query);
//
//                $data = $this->getData();
//
//                $i = 0;
//                while ($row = $result->fetchAssoc()) {
//                    $this->assertSame($data[$i][0], $row['domain']);
//                    $this->assertSame($data[$i][1], $row['tld']);
//                    $i++;
//                }
//
//                $ch->push($result);
//            } catch (\Throwable $e) {
//                $ch->push($e);
//            }
//        };
//
//        Coroutine::create($callback, "SELECT * FROM test", $successfulCh);
//        Coroutine::create($callback, "SELECT & FROM test", $failingCh);
//
//        $successful = $successfulCh->pop();
//        $failing = $failingCh->pop();
//
//        $this->assertInstanceOf(ResultSet::class, $successful);
//        $this->assertInstanceOf(QueryError::class, $failing);
//    }

    public function testSimultaneousQueryAndPrepare(): Promise
    {
        $promises = [];
        $promises[] = new Coroutine((function () {
            /** @var \Amp\Postgres\ResultSet $result */
            $result = yield $this->connection->query("SELECT * FROM test");

            $data = $this->getData();

            for ($i = 0; yield $result->advance(); ++$i) {
                $row = $result->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        })());

        $promises[] = new Coroutine((function () {
            /** @var Statement $statement */
            $statement = (yield $this->connection->prepare("SELECT * FROM test"));

            /** @var \Amp\Postgres\ResultSet $result */
            $result = yield $statement->execute();

            $data = $this->getData();

            for ($i = 0; yield $result->advance(); ++$i) {
                $row = $result->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        })());

        return Promise\all($promises);
    }

    public function testSimultaneousPrepareAndExecute(): Promise
    {
        $promises[] = new Coroutine((function () {
            /** @var Statement $statement */
            $statement = yield $this->connection->prepare("SELECT * FROM test");

            /** @var \Amp\Postgres\ResultSet $result */
            $result = yield $statement->execute();

            $data = $this->getData();

            for ($i = 0; yield $result->advance(); ++$i) {
                $row = $result->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        })());

        $promises[] = new Coroutine((function () {
            /** @var \Amp\Postgres\ResultSet $result */
            $result = yield $this->connection->execute("SELECT * FROM test");

            $data = $this->getData();

            for ($i = 0; yield $result->advance(); ++$i) {
                $row = $result->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        })());

        return Promise\all($promises);
    }

    public function testTransaction(): \Generator
    {
        $isolation = SqlTransaction::ISOLATION_COMMITTED;

        /** @var \Amp\Postgres\Transaction $transaction */
        $transaction = yield $this->connection->beginTransaction($isolation);

        $this->assertInstanceOf(Transaction::class, $transaction);

        $data = $this->getData()[0];

        $this->assertTrue($transaction->isAlive());
        $this->assertTrue($transaction->isActive());
        $this->assertSame($isolation, $transaction->getIsolationLevel());

        yield $transaction->createSavepoint('test');

        $statement = yield $transaction->prepare("SELECT * FROM test WHERE domain=:domain");
        $result = yield $statement->execute(['domain' => $data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $result = yield $transaction->execute("SELECT * FROM test WHERE domain=\$1 FOR UPDATE", [$data[0]]);

        $this->assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        yield $transaction->rollbackTo('test');

        yield $transaction->commit();

        $this->assertFalse($transaction->isAlive());
        $this->assertFalse($transaction->isActive());

        try {
            $result = yield $transaction->execute("SELECT * FROM test");
            $this->fail('Query should fail after transaction commit');
        } catch (TransactionError $exception) {
            // Exception expected.
        }
    }

    public function testListen(): \Generator
    {
        $channel = "test";
        /** @var \Amp\Postgres\Listener $listener */
        $listener = yield $this->connection->listen($channel);

        $this->assertInstanceOf(Listener::class, $listener);
        $this->assertSame($channel, $listener->getChannel());

        Loop::delay(100, function () use ($channel) {
            yield $this->connection->query(\sprintf("NOTIFY %s, '%s'", $channel, '0'));
            yield $this->connection->query(\sprintf("NOTIFY %s, '%s'", $channel, '1'));
        });

        $count = 0;
        Loop::delay(200, function () use ($listener) {
            $listener->unlisten();
        });

        while (yield $listener->advance()) {
            $this->assertSame($listener->getCurrent()->payload, (string)$count++);
        }

        $this->assertSame(2, $count);
    }

    /**
     * @depends testListen
     */
    public function testNotify(): \Generator
    {
        $channel = "test";
        /** @var \Amp\Postgres\Listener $listener */
        $listener = yield $this->connection->listen($channel);

        Loop::delay(100, function () use ($channel) {
            yield $this->connection->notify($channel, '0');
            yield $this->connection->notify($channel, '1');
        });

        $count = 0;
        Loop::delay(200, function () use ($listener) {
            $listener->unlisten();
        });

        while (yield $listener->advance()) {
            $this->assertSame($listener->getCurrent()->payload, (string)$count++);
        }

        $this->assertSame(2, $count);
    }

    /**
     * @depends testListen
     */
    public function testListenOnSameChannel(): Promise
    {
        $this->expectException(QueryError::class);
        $this->expectExceptionMessage('Already listening on channel');

        $channel = "test";
        return Promise\all([$this->connection->listen($channel), $this->connection->listen($channel)]);
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