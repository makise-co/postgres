<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Contracts;

use MakiseCo\Postgres\Notification;

interface Listener
{
    /**
     * @return Notification|null null when listener is gracefully closed
     *
     * @throws \MakiseCo\SqlCommon\Exception\ConnectionException when connection to the database is closed
     */
    public function getNotification(): ?Notification;

    /**
     * @return string Channel name.
     */
    public function getChannel(): string;

    /**
     * @return bool
     */
    public function isListening(): bool;

    /**
     * Unlistens from the channel. No more values will be emitted from this listener.
     *
     * @throws \Error If this method was previously invoked.
     */
    public function unlisten(): void;
}
