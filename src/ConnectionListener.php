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
use Error;
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use Swoole\Coroutine\Channel;

final class ConnectionListener implements Listener
{
    private Channel $iterator;

    private string $channel;

    private ?Closure $unlisten;

    /**
     * @param Channel $iterator Iterator emitting notificatons on the channel.
     * @param string $channel Channel name.
     * @param Closure $unlisten Function invoked to unlisten from the channel.
     */
    public function __construct(Channel $iterator, string $channel, Closure $unlisten)
    {
        $this->iterator = $iterator;
        $this->channel = $channel;
        $this->unlisten = $unlisten;
    }

    public function __destruct()
    {
        if ($this->unlisten !== null) {
            $this->unlisten();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getNotification(): ?Notification
    {
        $notification = $this->iterator->pop();

        // channel is closed
        if ($notification === false && $this->iterator->errCode === -2) {
            return null;
        }

        if ($notification instanceof ConnectionException) {
            if ($this->unlisten !== null) {
                $this->unlisten();
            }

            throw $notification;
        }

        return $notification;
    }

    /**
     * @return string Channel name.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @return bool
     */
    public function isListening(): bool
    {
        return $this->unlisten !== null;
    }

    /**
     * Unlistens from the channel. No more values will be emitted from this listener.
     *
     * @throws Error If this method was previously invoked.
     */
    public function unlisten(): void
    {
        if ($this->unlisten === null) {
            throw new Error("Already unlistened on this channel");
        }

        ($this->unlisten)($this->channel);
        $this->unlisten = null;
    }
}
