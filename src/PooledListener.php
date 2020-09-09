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
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\SqlCommon\Exception\FailureException;

class PooledListener implements Listener
{
    private Listener $listener;
    private ?Closure $release;

    public function __construct(Listener $listener, Closure $release)
    {
        if (!$listener->isListening()) {
            $release();

            throw new FailureException('Listener is dead');
        }

        $this->listener = $listener;
        $this->release = $release;
    }

    public function __destruct()
    {
        if ($this->listener->isListening()) {
            $this->unlisten();

            return;
        }

        // if listener was closed directly in connection lets call release
        if ($this->release !== null) {
            $release = $this->release;
            $this->release = null;
            $release();
        }
    }

    /**
     * @inheritDoc
     */
    public function getNotification(): ?Notification
    {
        return $this->listener->getNotification();
    }

    /**
     * @inheritDoc
     */
    public function getChannel(): string
    {
        return $this->listener->getChannel();
    }

    /**
     * @inheritDoc
     */
    public function isListening(): bool
    {
        return $this->listener->isListening();
    }

    /**
     * @inheritDoc
     */
    public function unlisten(): void
    {
        if ($this->release === null) {
            throw new \Error("Already unlistened on this channel");
        }

        $release = $this->release;
        $this->release = null;

        try {
            $this->listener->unlisten();
        } finally {
            $release();
        }
    }
}
