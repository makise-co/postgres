<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Closure;
use Swoole\Coroutine\Channel;
use Throwable;

final class Listener
{
    private string $listenChannel;
    private Channel $chan;
    private Closure $unlisten;
    private bool $isClosed = false;

    public function __construct(string $listenChannel, Channel $chan, Closure $unlisten)
    {
        $this->chan = $chan;
        $this->unlisten = $unlisten;
        $this->listenChannel = $listenChannel;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param float $timeout maximum wait time for notification (seconds)
     * @return Notification|null null is returned where listener was closed
     * @throws Exception\ConnectionException when connect is closed
     * @throws Exception\FailureException when listener is closed
     * @throws Throwable on unknown state
     */
    public function getNotification(float $timeout = 0): ?Notification
    {
        if ($this->isClosed) {
            throw new Exception\FailureException('Listener is closed');
        }

        $res = $this->chan->pop($timeout);
        if (false === $res) {
            // channel is closed
            if (-2 === $this->chan->errCode) {
                $this->isClosed = true;
            }

            return null;
        }

        if ($res instanceof Exception\ConnectionException) {
            $this->isClosed = true;

            throw $res;
        }

        if ($res instanceof Throwable) {
            throw $res;
        }

        return $res;
    }

    public function getChannel(): string
    {
        return $this->listenChannel;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function close(): void
    {
        if ($this->isClosed) {
            return;
        }

        $this->isClosed = true;

        $this->chan->close();
        ($this->unlisten)();
    }
}
