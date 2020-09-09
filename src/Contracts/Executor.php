<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Contracts;

use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\Executor as SqlExecutor;
use MakiseCo\SqlCommon\Exception;

interface Executor extends SqlExecutor
{
    /**
     * @param string $channel Channel name.
     * @param string $payload Notification payload.
     *
     * @return CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     */
    public function notify(string $channel, string $payload = ""): CommandResult;
}
