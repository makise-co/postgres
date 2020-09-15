<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Swoole;

use MakiseCo\Postgres\Internal;
use MakiseCo\SqlCommon\Contracts\Statement;

final class SwooleStatement implements Statement
{
    private SwooleHandle $handle;

    private string $name;

    private string $sql;

    private array $params;

    private $lastUsedAt;

    public function __construct(SwooleHandle $handle, string $name, string $sql, array $params)
    {
        $this->handle = $handle;
        $this->name = $name;
        $this->sql = $sql;
        $this->params = $params;
        $this->lastUsedAt = \time();
    }

    public function __destruct()
    {
        $this->handle->statementDeallocate($this->name);
    }

    /**
     * @inheritDoc
     */
    public function execute(array $params = [])
    {
        $this->lastUsedAt = \time();

        return $this->handle->statementExecute($this->name, Internal\replaceNamedParams($params, $this->params));
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
    public function isAlive(): bool
    {
        return $this->handle->isAlive();
    }

    /**
     * @inheritDoc
     */
    public function getLastUsedAt(): int
    {
        return $this->lastUsedAt;
    }
}
