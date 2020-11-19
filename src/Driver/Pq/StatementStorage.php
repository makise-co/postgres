<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\EvPrimitives\Lock;
use pq;
use Throwable;

/**
 * @internal
 */
final class StatementStorage
{
    public int $refCount = 1;

    /**
     * Synchronization object to allocate/deallocate statement
     *
     * @var Lock
     */
    public Lock $lock;

    /** @var bool Indicates that the statement is beign allocated */
    public bool $isAllocating = true;

    /** @var bool Indicates that the statement is beign deallocated */
    public bool $isDeallocating = false;

    public pq\Statement $statement;

    public string $sql;

    /** @var Throwable|null Allocation/De-allocation error */
    public ?Throwable $error = null;

    public function __construct()
    {
        $this->lock = new Lock();
    }
}
