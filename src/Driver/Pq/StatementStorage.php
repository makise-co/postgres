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
use Swoole\Coroutine;
use Throwable;

/**
 * @internal
 */
final class StatementStorage
{
    public int $refCount = 1;

    /**
     * Is awaiting database results for prepare?
     */
    public bool $promise = true;

    public pq\Statement $statement;

    public string $sql;

    /**
     * Coroutine list which wants to get the same statement while it is not prepared yet
     *
     * @var int[]
     */
    private array $queue = [];

    /**
     * Error that will be received by all blocked coroutines
     */
    private ?Throwable $error = null;

    public function subscribe(): void
    {
        $this->queue[Coroutine::getCid()] = true;

        Coroutine::yield();

        if (null !== $this->error) {
            throw $this->error;
        }
    }

    public function wakeupSubscribers(?Throwable $error): void
    {
        $this->error = $error;

        foreach ($this->queue as $cid => $item) {
            unset($this->queue[$cid]);

            Coroutine::resume($cid);
        }
    }
}
