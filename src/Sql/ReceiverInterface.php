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

namespace MakiseCo\Postgres\Sql;

use Closure;
use MakiseCo\Postgres\Exception;

interface ReceiverInterface
{
    /**
     * @param string $channel Channel name.
     * @param Closure|null $callable Notifications receiver callback.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return ListenerInterface|null When callable is null - Listener object is returned
     *      When callable is not null - null is returned
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function listen(string $channel, ?Closure $callable, float $timeout = 0): ?ListenerInterface;
}
