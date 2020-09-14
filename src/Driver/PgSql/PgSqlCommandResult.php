<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\PgSql;

use MakiseCo\SqlCommon\Contracts\CommandResult;

final class PgSqlCommandResult implements CommandResult
{
    /** @var resource PostgreSQL result resource. */
    private $handle;

    /**
     * @param resource $handle PostgreSQL result resource.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Frees the result resource.
     */
    public function __destruct()
    {
        \pg_free_result($this->handle);
    }

    /**
     * @return int Number of rows affected by the INSERT, UPDATE, or DELETE query.
     */
    public function getAffectedRowCount(): int
    {
        return \pg_affected_rows($this->handle);
    }

    /**
     * @return string
     * @deprecated This is not meant to be used to get the last insertion ID. Use `INSERT ... RETURNING column_name`
     *             to get the last auto-increment ID.
     *
     * $sql = "INSERT INTO person (lastname, firstname) VALUES (?, ?) RETURNING id;"
     * $statement = yield $pool->prepare($sql);
     * $result = yield $statement->execute(['Doe', 'John']);
     * if (!yield $result->advance()) {
     *     throw new \RuntimeException("Insertion failed");
     * }
     * $id = $result->getCurrent()['id'];
     *
     */
    public function getLastOid(): string
    {
        return (string)\pg_last_oid($this->handle);
    }
}
