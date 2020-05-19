<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Closure;
use MakiseCo\Postgres\Sql\ListenerInterface;
use Throwable;

class PooledListener implements ListenerInterface
{
    private Listener $listener;
    private ?Closure $release;

    public function __construct(Listener $listener, Closure $release)
    {
        if (!$listener->isListening()) {
            $release();
            $this->listener = $listener;
        } else {
            $this->listener = $listener;
            $this->release = $release;
        }
    }

    public function __destruct()
    {
        $this->unlisten();
    }

    public function getNotification(float $timeout = 0): ?Notification
    {
        return $this->listener->getNotification($timeout);
    }

    public function getChannel(): string
    {
        return $this->listener->getChannel();
    }

    public function isListening(): bool
    {
        return $this->listener->isListening();
    }

    public function unlisten(): void
    {
        if (!$this->release) {
            return;
        }

        $err = null;

        if ($this->listener->isListening()) {
            try {
                $this->listener->unlisten();
            } catch (Throwable $e) {
                $err = $e;
            }
        }

        $release = $this->release;
        $this->release = null;

        $release();

        if ($err) {
            throw $err;
        }
    }
}
