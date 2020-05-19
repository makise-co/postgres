<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Sql;

use MakiseCo\Postgres\CommandResult;
use MakiseCo\Postgres\Exception\FailureException;
use MakiseCo\Postgres\ResultSet;

interface StatementInterface
{
    /**
     * Executes the named statement using the given parameters.
     *
     * @param array $params Statement parameters
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return ResultSet|CommandResult
     * @throws FailureException when statement not found
     */
    public function execute(array $params = [], float $timeout = 0);

    public function isAlive(): bool;

    public function getQuery(): string;
}
