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

class PqRawCommandResult implements CommandResult
{
    private int $affectedRowCount;

    public function __construct(int $affectedRowCount = 0)
    {
        $this->affectedRowCount = $affectedRowCount;
    }

    public function getAffectedRowCount(): int
    {
        return $this->affectedRowCount;
    }
}
