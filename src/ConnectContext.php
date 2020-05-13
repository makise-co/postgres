<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

final class ConnectContext
{
    public int $cid = 0;
    public int $timerId = 0;
    public int $state = self::STATE_CONNECTING;

    public const STATE_CONNECTING = 0;
    public const STATE_CONNECTED = 1;
    public const STATE_CONNECTION_ERROR = 2;
    public const STATE_CONNECTION_TIMEOUT = 3;
}