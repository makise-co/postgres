<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use pq;

/**
 * @internal
 */
final class PqStatementStorage
{
    public int $refCount = 1;

    /**
     * Synchronization object to allocate/deallocate statement
     */
    public ?Promise $promise;

    public pq\Statement $statement;

    public string $sql;
}
