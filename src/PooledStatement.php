<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Closure;
use MakiseCo\Postgres\Sql\StatementInterface;

class PooledStatement implements StatementInterface
{
    private ?Statement $statement;
    private Closure $release;
    private int $refCount = 1;

    public function __construct(Statement $statement, Closure $release)
    {
        $this->release = $release;

        if ($statement->isAlive()) {
            $this->statement = $statement;

            $refCount = &$this->refCount;
            $this->release = static function () use (&$refCount, $release) {
                if (--$refCount === 0) {
                    $release();
                }
            };
        } else {
            $release();
            $this->statement = null;
        }
    }

    public function __destruct()
    {
        if ($this->release) {
            ($this->release)();
        }
    }

    public function execute(array $params = [], float $timeout = 0)
    {
        $result = $this->statement->execute($params, $timeout);

        if ($result instanceof ResultSet) {
            ++$this->refCount;

            return new PooledResultSet($result, $this->release);
        }

        return $result;
    }

    public function isAlive(): bool
    {
        return $this->statement->isAlive();
    }

    public function getQuery(): string
    {
        return $this->statement->getQuery();
    }
}
