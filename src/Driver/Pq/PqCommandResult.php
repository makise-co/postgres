<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\SqlCommon\Contracts\CommandResult;
use pq;

final class PqCommandResult implements CommandResult
{
    /** @var pq\Result PostgreSQL result object. */
    private pq\Result $result;

    /**
     * @param \pq\Result $result PostgreSQL result object.
     */
    public function __construct(pq\Result $result)
    {
        $this->result = $result;
    }

    /**
     * @return int Number of rows affected by the INSERT, UPDATE, or DELETE query.
     */
    public function getAffectedRowCount(): int
    {
        return $this->result->affectedRows;
    }
}
