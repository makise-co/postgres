<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

namespace MakiseCo\Postgres;

final class Notification
{
    /** @var string Channel name. */
    public string $channel;

    /** @var int PID of message source. */
    public int $pid;

    /** @var string Message payload */
    public string $payload;
}
