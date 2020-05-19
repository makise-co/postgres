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
use stdClass;

class PooledResultSet extends ResultSet
{
    private ResultSet $resultSet;
    private ?Closure $release;

    public function __construct(ResultSet $resultSet, Closure $release)
    {
        $this->resultSet = $resultSet;
        $this->release = $release;
    }

    public function __destruct()
    {
        $this->close();
    }

    protected function close(): void
    {
        if (!$this->release) {
            return;
        }

        $release = $this->release;
        $this->release = null;

        $release();
    }

    public function fetch(int $fetchStyle = self::DEFAULT_FETCH_STYLE)
    {
        $result = $this->resultSet->fetch($fetchStyle);
        if (null === $result) {
            $this->close();
        }

        return $result;
    }

    public function fetchAssoc(): ?array
    {
        $result = $this->resultSet->fetchAssoc();
        if (null === $result) {
            $this->close();
        }

        return $result;
    }

    public function fetchObject(): ?stdClass
    {
        $result = $this->resultSet->fetchObject();
        if (null === $result) {
            $this->close();
        }

        return $result;
    }

    public function fetchArray(): ?array
    {
        $result = $this->resultSet->fetchArray();
        if (null === $result) {
            $this->close();
        }

        return $result;
    }

    public function fetchCol($col, &$ref): ?bool
    {
        $result = $this->resultSet->fetchCol($col, $ref);
        if (null === $result) {
            $this->close();
        }

        return $result;
    }

    public function fetchAll(int $fetchStyle = self::DEFAULT_FETCH_STYLE): array
    {
        $result = $this->resultSet->fetchAll($fetchStyle);
        if ([] === $result) {
            $this->close();
        }

        return $result;
    }

    public function fetchAllCols($col): array
    {
        $result = $this->resultSet->fetchAllCols($col);
        if ([] === $result) {
            $this->close();
        }

        return $result;
    }

    public function fetchBound(array $map = []): ?array
    {
        $result = $this->resultSet->fetchBound($map);
        if (null === $result) {
            $this->close();
        }

        return $result;
    }

    public function getFieldCount(): int
    {
        return $this->resultSet->getFieldCount();
    }
}
