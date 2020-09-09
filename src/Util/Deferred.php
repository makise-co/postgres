<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Util;

use SplQueue;
use Swoole\Coroutine;
use Throwable;

final class Deferred
{
    /**
     * Coroutine ID which awaiting query execution result
     */
    protected int $cid = 0;

    /**
     * Failed query execution result
     */
    protected ?Throwable $error = null;

    /**
     * Successful query execution result
     *
     * @var mixed
     */
    protected $value;

    /**
     * Blocked coroutines list
     */
    protected SplQueue $queue;

    /**
     * Is handle performing unbuffered results fetching?
     */
    protected bool $locked = false;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    /**
     * Wait for query results
     *
     * @return mixed
     *
     * @throws Throwable
     */
    public function wait()
    {
        $this->cid = Coroutine::getCid();

        Coroutine::yield();

        $this->cid = 0;

        if (null !== $this->error) {
            $error = $this->error;
            $this->error = null;

            throw $error;
        }

        $value = $this->value;
        $this->value = null;

        return $value;
    }

    /**
     * Used for unbuffered mode to prevent another queries execution
     * until unbuffered results fetching done
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    /**
     * Used for unbuffered mode to prevent another queries execution
     * until unbuffered results fetching done
     */
    public function unlock(): void
    {
        $this->locked = false;

        $this->wakeupSubscribers();
    }

    /**
     * Block coroutine until previous query will be completed
     */
    public function subscribe(): void
    {
        $this->queue->enqueue(Coroutine::getCid());

        Coroutine::yield();
    }

    /**
     * Block coroutine until previous query will be completed
     */
    private function wakeupSubscribers(): void
    {
        if ($this->queue->isEmpty()) {
            return;
        }

        Coroutine::resume($this->queue->dequeue());
    }

    public function isWaiting(): bool
    {
        return $this->cid !== 0 || $this->locked;
    }

    /**
     * Used to continue coroutines execution when query results fetched
     *
     * @param mixed $value
     */
    public function resolve($value): void
    {
        $this->value = $value;

        Coroutine::resume($this->cid);

        $this->wakeupSubscribers();
    }

    /**
     * Used to continue coroutines execution when query execution fails
     *
     * @param Throwable $e
     */
    public function fail(Throwable $e): void
    {
        $this->error = $e;

        Coroutine::resume($this->cid);

        $this->wakeupSubscribers();
    }
}
