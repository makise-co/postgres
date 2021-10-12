<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\Postgres\Internal;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\SqlCommon\Exception\ConnectionException;

final class PqStatement implements Statement
{
    /**
     * @var \WeakReference<PqHandle>
     */
    private \WeakReference $handle;

    private string $name;

    private string $sql;

    private array $params;

    private $lastUsedAt;

    /**
     * @param \WeakReference<PqHandle> $handle
     * @param string $name Statement name.
     * @param string $sql Original prepared SQL query.
     * @param string[] $params Parameter indices to parameter names.
     */
    public function __construct(\WeakReference $handle, string $name, string $sql, array $params)
    {
        $this->handle = $handle;
        $this->name = $name;
        $this->params = $params;
        $this->sql = $sql;
        $this->lastUsedAt = \time();
    }

    public function __destruct()
    {
        /** @var PqHandle|null $handle */
        $handle = $this->handle->get();
        if ($handle === null) {
            return; // Connection dead.
        }

        $handle->statementDeallocate($this->name);
    }

    /** {@inheritdoc} */
    public function isAlive(): bool
    {
        /** @var PqHandle|null $handle */
        $handle = $this->handle->get();
        if ($handle === null) {
            return false; // Connection dead.
        }

        return $handle->isAlive();
    }

    /** {@inheritdoc} */
    public function getQuery(): string
    {
        return $this->sql;
    }

    /** {@inheritdoc} */
    public function getLastUsedAt(): int
    {
        return $this->lastUsedAt;
    }

    /** {@inheritdoc} */
    public function execute(array $params = [])
    {
        /** @var PqHandle|null $handle */
        $handle = $this->handle->get();
        if ($handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $this->lastUsedAt = \time();

        return $handle->statementExecute(
            $this->name,
            Internal\replaceNamedParams($params, $this->params),
        );
    }
}
