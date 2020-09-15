<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\PgSql;

use MakiseCo\Postgres\Internal;
use MakiseCo\SqlCommon\Contracts\Statement;

final class PgSqlStatement implements Statement
{
    private PgSqlHandle $handle;

    private string $name;

    private string $sql;

    private array $params;

    private $lastUsedAt;

    /**
     * @param PgSqlHandle $handle
     * @param string $name Statement name.
     * @param string $sql Original prepared SQL query.
     * @param string[] $params Parameter indices to parameter names.
     */
    public function __construct(PgSqlHandle $handle, string $name, string $sql, array $params)
    {
        $this->handle = $handle;
        $this->name = $name;
        $this->params = $params;
        $this->sql = $sql;
        $this->lastUsedAt = \time();
    }

    public function __destruct()
    {
        $this->handle->statementDeallocate($this->name);
    }

    /**
     * @inheritDoc
     */
    public function isAlive(): bool
    {
        return $this->handle->isAlive();
    }

    /**
     * @inheritDoc
     */
    public function getLastUsedAt(): int
    {
        return $this->handle->getLastUsedAt();
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): string
    {
        return $this->sql;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $params = [])
    {
        $this->lastUsedAt = \time();

        return $this->handle->statementExecute($this->name, Internal\replaceNamedParams($params, $this->params));
    }
}
