<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Sql;

use MakiseCo\Postgres\Exception;
use MakiseCo\Postgres\Notification;

interface ListenerInterface
{
    /**
     * @param float $timeout maximum wait time for notification (seconds)
     * @return Notification|null null is returned where listener was closed
     * @throws Exception\ConnectionException when connect is closed
     * @throws Exception\FailureException when listener is closed
     * @throws \Throwable on unknown state
     */
    public function getNotification(float $timeout = 0): ?Notification;

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
