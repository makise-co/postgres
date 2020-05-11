<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use pq\Statement as PqStatement;

class Statement
{
    private PqHandle $handle;

    private PqStatement $pqStatement;

    private string $name;

    private string $sql;

    private array $params;

    /** @var int */
    private int $lastUsedAt = 0;

    private bool $closed = false;

    /**
     * @param PqHandle $handle statement's handling connection
     * @param PqStatement $pqStatement statement from pq
     * @param string $name Statement name.
     * @param string $sql Original prepared SQL query.
     * @param string[] $params Parameter indices to parameter names.
     */
    public function __construct(PqHandle $handle, PqStatement $pqStatement, string $name, string $sql, array $params)
    {
        $this->handle = $handle;
        $this->pqStatement = $pqStatement;
        $this->name = $name;
        $this->params = $params;
        $this->sql = $sql;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->handle->statementDeallocate($this->name);
    }

    public function isAlive(): bool
    {
        return $this->handle->isConnected();
    }

    public function getQuery(): string
    {
        return $this->sql;
    }

    public function getLastUsedAt(): int
    {
        return $this->lastUsedAt;
    }

    /**
     * @param array $params
     * @return BufferedResultSet|CommandResult|ResultSet|UnbufferedResultSet
     * @throws Exception\FailureException
     */
    public function execute(array $params = [])
    {
        if ($this->closed) {
            throw new Exception\FailureException("Statement is closed");
        }

        $this->lastUsedAt = \time();

        return $this->handle->statementExecute($this->name, Internal\replaceNamedParams($params, $this->params));
    }

    public function getPqStatement(): PqStatement
    {
        return $this->pqStatement;
    }
}