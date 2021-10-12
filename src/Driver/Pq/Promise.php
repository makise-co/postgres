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

class Promise
{
    private \Closure $func;

    /**
     * @var mixed
     */
    private $result;

    private bool $awaited = false;
    private bool $awaiting = false;
    private array $awaitors = [];

    public function __construct(\Closure $func)
    {
        $this->chan = new Channel(1);
        $this->func = $func;
    }

    /**
     * Wait until promise result is resolved
     */
    public function wait(): void
    {
        if ($this->awaited) {
            return;
        }

        if ($this->awaiting) {
            $this->awaitors[] = Coroutine::getCid();
            Coroutine::yield();

            return;
        }

        $this->awaiting = true;

        try {
            $this->result = ($this->func)();
        } catch (\Throwable $e) {
            $this->result = $e;
        }

        $this->awaited = true;

        $awaiters = $this->awaitors;
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
}
