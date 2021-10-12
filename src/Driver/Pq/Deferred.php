<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Event;

/**
 * Deferred is a container for a promise that is resolved using the resolve() and fail() methods of this object.
 */
class Deferred
{
    private Channel $chan;

    /**
     * @var mixed
     */
    private $result;

    private bool $isResolved = false;
    private bool $awaiting = false;
    private array $awaiters = [];

    public function __construct()
    {
        $this->chan = new Channel(1);
    }

    public function __destruct()
    {
        if (!$this->isResolved) {
            $this->chan->close();
        }
    }

    public function wait(): void
    {
        if ($this->isResolved) {
            return;
        }

        if ($this->awaiting) {
            $this->awaiters[] = Coroutine::getCid();
            Coroutine::yield();

            return;
        }

        $this->awaiting = true;

        $this->result = $this->chan->pop();
        $this->chan->close();
        $this->isResolved = true;

        // Wake-up awaiters
        $awaiters = $this->awaiters;
        Event::defer(static function () use ($awaiters) {
            foreach ($awaiters as $awaitor) {
                Coroutine::resume($awaitor);
            }
        });
    }

    /**
     * Wait until promise resolved and return result
     *
     * @return mixed success result
     * @throws \Throwable error result
     */
    public function getResult()
    {
        $this->wait();

        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }

    /**
     * Fulfill the promise with the given value.
     *
     * @param mixed $value
     *
     * @return void
     */
    public function resolve($value = null): void
    {
        $this->chan->push($value);
    }

    /**
     * Fails the promise the the given reason.
     *
     * @param \Throwable $reason
     *
     * @return void
     */
    public function fail(\Throwable $reason): void
    {
        $this->chan->push($reason);
    }

    /**
     * @return bool True if the promise has been resolved.
     */
    public function isResolved(): bool
    {
        return $this->isResolved;
    }
}
