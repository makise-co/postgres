<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

final class Notification
{
    /** @var string Channel name. */
    public string $channel;

    /** @var int PID of message source. */
    public int $pid;

    /** @var string Message payload */
    public string $payload;

    public function __construct(string $channel, int $pid, string $payload)
    {
        $this->channel = $channel;
        $this->pid = $pid;
        $this->payload = $payload;
    }
}
