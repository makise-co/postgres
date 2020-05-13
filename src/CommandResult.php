<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use pq\Result;

final class CommandResult
{
    /** @var Result PostgreSQL result object. */
    private Result $result;

    /**
     * @param Result $result PostgreSQL result object.
     */
    public function __construct(Result $result)
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
