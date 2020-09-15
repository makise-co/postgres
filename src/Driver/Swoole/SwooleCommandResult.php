<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Swoole;

use MakiseCo\SqlCommon\Contracts\CommandResult;
use Swoole\Coroutine\PostgreSQL;

final class SwooleCommandResult implements CommandResult
{
    private PostgreSQL $handle;

    /**
     * @var resource
     */
    private $result;

    /**
     * @param PostgreSQL $handle
     * @param resource $result
     */
    public function __construct(PostgreSQL $handle, $result)
    {
        $this->handle = $handle;
        $this->result = $result;
    }

    /**
     * @inheritDoc
     */
    public function getAffectedRowCount(): int
    {
        return $this->handle->affectedRows($this->result);
    }
}
