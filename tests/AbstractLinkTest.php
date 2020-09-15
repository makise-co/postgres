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
use MakiseCo\Postgres\Contracts\Transaction;
use MakiseCo\Postgres\Exception\QueryExecutionError;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use MakiseCo\SqlCommon\Exception\QueryError;
use MakiseCo\SqlCommon\Exception\TransactionError;
use Swoole\Coroutine;
use Swoole\Timer;

abstract class AbstractLinkTest extends CoroTestCase
{
    protected Link $connection;

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

    /**
     * @param string $connectionString
     *
     * @return Link Connection or Link object to be tested.
     */
    abstract public function createLink(string $connectionString): Link;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createLink(
            ConnectionConfigProvider::getString()
        );
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testQueryWithTupleResult(): void
    {
        $result = $this->connection->query("SELECT * FROM test");

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        $data = $this->getData();

        for ($i = 0; $row = $result->fetch(); ++$i) {
            self::assertSame($data[$i][0], $row['domain']);
            self::assertSame($data[$i][1], $row['tld']);
        }
    }

    public function testQueryWithUnconsumedTupleResult(): void
    {
        $result = $this->connection->query("SELECT * FROM test");

        self::assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $result = $this->connection->query("SELECT * FROM test");

        self::assertInstanceOf(ResultSet::class, $result);

        $data = $this->getData();

        for ($i = 0; $row = $result->fetch(); ++$i) {
            self::assertSame($data[$i][0], $row['domain']);
            self::assertSame($data[$i][1], $row['tld']);
        }
    }

    public function testQueryWithCommandResult(): void
    {
        /** @var CommandResult $result */
        $result = $this->connection->query("INSERT INTO test VALUES ('canon', 'jp')");

        self::assertInstanceOf(CommandResult::class, $result);
        self::assertSame(1, $result->getAffectedRowCount());
    }

    public function testQueryWithEmptyQuery(): void
    {
        $this->expectException(QueryError::class);

        $this->connection->query('');
    }

    public function testQueryWithSyntaxError(): void
    {
        try {
            $result = $this->connection->query("SELECT & FROM test");
            self::fail(\sprintf("An instance of %s was expected to be thrown", QueryExecutionError::class));
        } catch (QueryExecutionError $exception) {
            $diagnostics = $exception->getDiagnostics();
            self::assertArrayHasKey("sqlstate", $diagnostics);
        }
    }

    public function testPrepare(): void
    {
        $query = "SELECT * FROM test WHERE domain=\$1";

        $statement = $this->connection->prepare($query);

        self::assertSame($query, $statement->getQuery());

        $data = $this->getData()[0];

        $result = $statement->execute([$data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareWithNamedParams(): void
    {
        $query = "SELECT * FROM test WHERE domain=:domain AND tld=:tld";

        $statement = $this->connection->prepare($query);

        $data = $this->getData()[0];

        self::assertSame($query, $statement->getQuery());

        $result = $statement->execute(['domain' => $data[0], 'tld' => $data[1]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareWithUnnamedParams(): void
    {
        $query = "SELECT * FROM test WHERE domain=? AND tld=?";

        $statement = $this->connection->prepare($query);

        $data = $this->getData()[0];

        self::assertSame($query, $statement->getQuery());

        $result = $statement->execute([$data[0], $data[1]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareWithNamedParamsWithDataAppearingAsNamedParam(): void
    {
        $query = "SELECT * FROM test WHERE domain=:domain OR domain=':domain'";

        $statement = $this->connection->prepare($query);

        $data = $this->getData()[0];

        self::assertSame($query, $statement->getQuery());

        $result = $statement->execute(['domain' => $data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
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

        $this->connection->prepare($query);
    }

    /**
     * @depends testPrepare
     */
    public function testPrepareSameQuery(): void
    {
        $sql = "SELECT * FROM test WHERE domain=\$1";

        $statement1 = $this->connection->prepare($sql);
        $statement2 = $this->connection->prepare($sql);

        self::assertInstanceOf(Statement::class, $statement1);
        self::assertInstanceOf(Statement::class, $statement2);

        unset($statement1);

        $data = $this->getData()[0];

        $result = $statement2->execute([$data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testPrepareSameQuery
     */
    public function testSimultaneousPrepareSameQuery(): void
    {
        $sql = "SELECT * FROM test WHERE domain=\$1";

        /**
         * @var Statement $statement1
         * @var Statement $statement2
         */
        $statement1 = null;
        $statement2 = null;

        $cid = Coroutine::getCid();

        Coroutine::create(
            function () use ($cid, $sql, &$statement1, &$statement2) {
                $statement1 = $this->connection->prepare($sql);

                if (null !== $statement1 && null !== $statement2) {
                    Coroutine::resume($cid);
                }
            }
        );

        Coroutine::create(
            function () use ($cid, $sql, &$statement1, &$statement2) {
                $statement2 = $this->connection->prepare($sql);

                if (null !== $statement1 && null !== $statement2) {
                    Coroutine::resume($cid);
                }
            }
        );

        Coroutine::yield();

        self::assertInstanceOf(Statement::class, $statement1);
        self::assertInstanceOf(Statement::class, $statement2);

        $data = $this->getData()[0];

        $result = $statement1->execute([$data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }

        unset($statement1);

        $result = $statement2->execute([$data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }
    }

    public function testPrepareSimilarQueryReturnsDifferentStatements(): void
    {
        /**
         * @var Statement $statement1
         * @var Statement $statement2
         */
        $statement1 = null;
        $statement2 = null;

        $cid = Coroutine::getCid();

        Coroutine::create(
            function () use ($cid, &$statement1, &$statement2) {
                $statement1 = $this->connection->prepare("SELECT * FROM test WHERE domain=\$1");

                if (null !== $statement1 && null !== $statement2) {
                    Coroutine::resume($cid);
                }
            }
        );

        Coroutine::create(
            function () use ($cid, &$statement1, &$statement2) {
                $statement2 = $this->connection->prepare("SELECT * FROM test WHERE domain=:domain");

                if (null !== $statement1 && null !== $statement2) {
                    Coroutine::resume($cid);
                }
            }
        );

        Coroutine::yield();

        self::assertInstanceOf(Statement::class, $statement1);
        self::assertInstanceOf(Statement::class, $statement2);

        self::assertNotSame($statement1, $statement2);

        $data = $this->getData()[0];

        $results = [];

        $results[] = $statement1->execute([$data[0]])->fetchAll();
        $results[] = $statement2->execute(['domain' => $data[0]])->fetchAll();

        foreach ($results as $result) {
            /** @var ResultSet $result */
            foreach ($result as $row) {
                self::assertSame($data[0], $row['domain']);
                self::assertSame($data[1], $row['tld']);
            }
        }
    }

    public function testPrepareThenExecuteWithUnconsumedTupleResult(): void
    {
        $statement = $this->connection->prepare("SELECT * FROM test");

        $result = $statement->execute();

        self::assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $result = $statement->execute();

        self::assertInstanceOf(ResultSet::class, $result);

        $data = $this->getData();

        for ($i = 0; $row = $result->fetch(); ++$i) {
            self::assertSame($data[$i][0], $row['domain']);
            self::assertSame($data[$i][1], $row['tld']);
        }
    }

    public function testExecute(): void
    {
        $data = $this->getData()[0];

        $result = $this->connection->execute("SELECT * FROM test WHERE domain=\$1", [$data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testExecute
     */
    public function testExecuteWithNamedParams(): void
    {
        $data = $this->getData()[0];

        $result = $this->connection->execute(
            "SELECT * FROM test WHERE domain=:domain",
            ['domain' => $data[0]]
        );

        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(2, $result->getFieldCount());

        while ($row = $result->fetch()) {
            self::assertSame($data[0], $row['domain']);
            self::assertSame($data[1], $row['tld']);
        }
    }

    /**
     * @depends testExecute
     */
    public function testExecuteWithInvalidParams(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Value for unnamed parameter at position 0 missing");

        $this->connection->execute("SELECT * FROM test WHERE domain=\$1");
    }

    /**
     * @depends testExecute
     */
    public function testExecuteWithInvalidNamedParams(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Value for named parameter 'domain' missing");

        $this->connection->execute("SELECT * FROM test WHERE domain=:domain", ['tld' => 'com']);
    }

    /**
     * @depends testQueryWithTupleResult
     */
    public function testSimultaneousQuery(): void
    {
        $cid = Coroutine::getCid();
        $cnt = 0;

        $callback = function (int $value) use ($cid, &$cnt) {
            $result = $this->connection->query("SELECT {$value} as value");

            if ($value) {
                Coroutine::sleep(0.1);
            }

            while ($row = $result->fetch()) {
                $this->assertEquals($value, $row['value']);
            }

            if (++$cnt === 2) {
                Coroutine::resume($cid);
            }
        };

        Coroutine::create($callback, 0);
        Coroutine::create($callback, 1);

        Coroutine::yield();
    }

    /**
     * @depends testSimultaneousQuery
     */
    public function testSimultaneousQueryWithOneFailing(): void
    {
        $cid = Coroutine::getCid();
        $cnt = 0;
        $results = [];

        $callback = function (string $query, string $key) use ($cid, &$cnt, &$results) {
            try {
                $results[$key] = $result = $this->connection->query($query);

                $data = $this->getData();

                for ($i = 0; $row = $result->fetch(); ++$i) {
                    $this->assertSame($data[$i][0], $row['domain']);
                    $this->assertSame($data[$i][1], $row['tld']);
                }
            } catch (\Throwable $e) {
                $results[$key] = $e;
            }

            if (++$cnt === 2) {
                Coroutine::resume($cid);
            }
        };

        Coroutine::create($callback, "SELECT * FROM test", 'successful');
        Coroutine::create($callback, "SELECT & FROM test", 'failing');

        Coroutine::yield();

        $successful = $results['successful'] ?? null;
        $failing = $results['failing'] ?? null;

        self::assertInstanceOf(ResultSet::class, $successful);
        self::assertInstanceOf(QueryError::class, $failing);
    }

    public function testSimultaneousQueryAndPrepare(): void
    {
        $cid = Coroutine::getCid();
        $cnt = 0;

        Coroutine::create(function () use ($cid, &$cnt) {
            $result = $this->connection->query("SELECT * FROM test");

            $data = $this->getData();

            for ($i = 0; $row = $result->fetch(); ++$i) {
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }

            if (++$cnt === 2) {
                Coroutine::resume($cid);
            }
        });

        Coroutine::create(function () use ($cid, &$cnt) {
            $statement = $this->connection->prepare("SELECT * FROM test");

            $result = $statement->execute();

            $data = $this->getData();

            for ($i = 0; $row = $result->fetch(); ++$i) {
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }

            if (++$cnt === 2) {
                Coroutine::resume($cid);
            }
        });

        Coroutine::yield();
    }

    public function testSimultaneousPrepareAndExecute(): void
    {
        $ch = new Coroutine\Channel();

        Coroutine::create(function () use ($ch) {
            $statement = $this->connection->prepare("SELECT * FROM test");

            $result = $statement->execute();

            $data = $this->getData();

            for ($i = 0; $row = $result->fetch(); ++$i) {
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }

            unset($statement);

            $ch->push(1);
        });

        Coroutine::create(function () use ($ch) {
            $result = $this->connection->execute("SELECT * FROM test");

            $data = $this->getData();

            for ($i = 0; $row = $result->fetch(); ++$i) {
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }

            $ch->push(1);
        });

        for ($i = 0; $i < 2; $i++) {
            $ch->pop();
        }
    }

    public function testTransaction(): void
    {
        $isolation = Transaction::ISOLATION_COMMITTED;

        $transaction = $this->connection->beginTransaction($isolation);

        self::assertInstanceOf(Transaction::class, $transaction);

        $data = $this->getData()[0];

        self::assertTrue($transaction->isAlive());
        self::assertTrue($transaction->isActive());
        self::assertSame($isolation, $transaction->getIsolationLevel());

        $transaction->createSavepoint('test');

        $statement = $transaction->prepare("SELECT * FROM test WHERE domain=:domain");
        $result = $statement->execute(['domain' => $data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $result = $transaction->execute("SELECT * FROM test WHERE domain=\$1 FOR UPDATE", [$data[0]]);

        self::assertInstanceOf(ResultSet::class, $result);

        unset($result); // Force destruction of result object.

        $transaction->rollbackTo('test');

        $transaction->commit();

        self::assertFalse($transaction->isAlive());
        self::assertFalse($transaction->isActive());

        try {
            $result = $transaction->execute("SELECT * FROM test");
            self::fail('Query should fail after transaction commit');
        } catch (TransactionError $exception) {
            // Exception expected.
        }
    }

    public function testListen(): void
    {
        $channel = "test";
        $listener = $this->connection->listen($channel);

        self::assertSame($channel, $listener->getChannel());

        Timer::after(100, function () use ($channel) {
            $this->connection->query(\sprintf("NOTIFY %s, '%s'", $channel, '0'));
            $this->connection->query(\sprintf("NOTIFY %s, '%s'", $channel, '1'));
        });

        $count = 0;
        Timer::after(200, static function () use ($listener) {
            $listener->unlisten();
        });

        while ($notification = $listener->getNotification()) {
            self::assertSame($notification->payload, (string)$count++);
        }

        self::assertSame(2, $count);
    }

    /**
     * @depends testListen
     */
    public function testListenInterruptedByClosedConnection(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('The connection was closed');

        $channel = "test";
        $listener = $this->connection->listen($channel);

        Timer::after(200, function () {
            $this->connection->close();
        });

        while ($notification = $listener->getNotification()) {
        }
    }

    /**
     * @depends testListen
     */
    public function testNotify(): void
    {
        $channel = "test";
        $listener = $this->connection->listen($channel);

        Timer::after(100, function () use ($channel) {
            $this->connection->notify($channel, '0');
            $this->connection->notify($channel, '1');
        });

        $count = 0;
        Timer::after(200, static function () use ($listener) {
            $listener->unlisten();
        });

        while ($notification = $listener->getNotification()) {
            self::assertSame($notification->payload, (string)$count++);
        }

        self::assertSame(2, $count);
    }

    /**
     * @depends testListen
     */
    public function testListenOnSameChannel(): void
    {
        $this->expectException(QueryError::class);
        $this->expectExceptionMessage('Already listening on channel');

        $cid = Coroutine::getCid();
        $cnt = 0;

        $ex = null;
        $channel = "test";

        $func = function () use ($channel, &$ex, $cid, &$cnt) {
            try {
                $this->connection->listen($channel);
            } catch (QueryError $e) {
                $ex = $e;
            } catch (\Throwable $e) {
            }

            if (++$cnt === 2) {
                Coroutine::resume($cid);
            }
        };

        Coroutine::create($func);
        Coroutine::create($func);

        Coroutine::yield();

        if (null !== $ex) {
            throw $ex;
        }
    }

    public function testQueryAfterErroredQuery(): void
    {
        try {
            $result = $this->connection->query("INSERT INTO test (domain, tld) VALUES ('github', 'com')");
        } catch (QueryExecutionError $exception) {
            // Expected exception due to duplicate key.
        }

        /** @var CommandResult $result */
        $result = $this->connection->query("INSERT INTO test (domain, tld) VALUES ('gitlab', 'com')");

        self::assertSame(1, $result->getAffectedRowCount());
    }
}
