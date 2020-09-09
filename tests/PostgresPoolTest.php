<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\PostgresPool;
use MakiseCo\SqlCommon\StatementPool;

class PostgresPoolTest extends CoroTestCase
{
    private PostgresPool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $config = ConnectionConfigProvider::getConfig();

        $this->pool = new PostgresPool($config);
        $this->pool->setMaxActive(4);
        $this->pool->setMinActive(0);
        $this->pool->setMaxIdleTime(5);
        $this->pool->setValidationInterval(10);
        $this->pool->setMaxWaitTime(5);

        $this->pool->init();
    }

    protected function tearDown(): void
    {
        $this->pool->close();

        parent::tearDown();
    }

    public function testClose(): void
    {
        /** @var StatementPool $statementPool */
        $statementPool = $this->pool->prepare('SELECT 1');

        $this->pool->close();

        self::assertSame(0, $this->pool->getIdleCount());
        self::assertSame(0, $this->pool->getTotalCount());

        unset($statementPool);
    }

    public function testQuery(): void
    {
        $result = $this->pool->query('SELECT 1');

        self::assertSame($this->pool->getTotalCount(), 1);
        self::assertSame($this->pool->getIdleCount(), 1);
    }

    public function testPrepare(): void
    {
        $statement = $this->pool->prepare('SELECT 1');

        self::assertSame($this->pool->getTotalCount(), 1);
        self::assertSame($this->pool->getIdleCount(), 0);

        $result = $statement->execute();
        unset($statement);

        self::assertSame($this->pool->getTotalCount(), 1);
        self::assertSame($this->pool->getIdleCount(), 1);

        self::assertSame(1, $result->fetch()['?column?']);
    }
}
