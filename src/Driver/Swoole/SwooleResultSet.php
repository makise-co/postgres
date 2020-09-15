<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Swoole;

use MakiseCo\SqlCommon\Contracts\ResultSet;
use stdClass;
use Swoole\Coroutine\PostgreSQL;

final class SwooleResultSet implements ResultSet
{
    private PostgreSQL $connection;

    /**
     * @var resource
     */
    private $result;

    /**
     * @param PostgreSQL $connection
     * @param resource $result
     */
    public function __construct(PostgreSQL $connection, $result)
    {
        $this->connection = $connection;
        $this->result = $result;
    }

    /**
     * @inheritDoc
     */
    public function getFieldCount(): int
    {
        return $this->connection->fieldCount($this->result);
    }

    public function getNumRows(): int
    {
        return $this->connection->numRows($this->result);
    }

    /**
     * @inheritDoc
     */
    public function fetch(int $fetchStyle = self::FETCH_ASSOC)
    {
        $result = false;

        if ($fetchStyle === self::FETCH_ASSOC) {
            $result = $this->connection->fetchAssoc($this->result);
        } elseif ($fetchStyle === self::FETCH_ARRAY) {
            $result = $this->connection->fetchRow($this->result);
        } elseif ($fetchStyle === self::FETCH_OBJECT) {
            $result = $this->connection->fetchObject($this->result);
        }

        if (false === $result) {
            return null;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function fetchAssoc(): ?array
    {
        return $this->fetch(self::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function fetchObject(): ?stdClass
    {
        return $this->fetch(self::FETCH_OBJECT);
    }

    /**
     * @inheritDoc
     */
    public function fetchArray(): ?array
    {
        return $this->fetch(self::FETCH_ARRAY);
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn($col, &$ref): ?bool
    {
        $row = $this->fetch(self::FETCH_ARRAY);
        if (null === $row) {
            return null;
        }

        if (!\array_key_exists($col, $row)) {
            return false;
        }

        $ref = $row[$col];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(int $fetchStyle = self::FETCH_ASSOC): array
    {
        $result = [];

        while ($row = $this->fetch($fetchStyle)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function isUnbuffered(): bool
    {
        return false;
    }
}
