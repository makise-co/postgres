<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\SqlCommon\Contracts\ResultSet;
use pq;
use stdClass;

final class PqBufferedResultSet implements ResultSet
{
    /** @var pq\Result */
    private pq\Result $result;

    /**
     * @param pq\Result $result PostgreSQL result object.
     */
    public function __construct(pq\Result $result)
    {
        $this->result = $result;
        $this->result->autoConvert = pq\Result::CONV_SCALAR | pq\Result::CONV_ARRAY;
    }

    public function getNumRows(): int
    {
        return $this->result->numRows;
    }

    public function getFieldCount(): int
    {
        return $this->result->numCols;
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(int $fetchStyle = self::FETCH_ASSOC)
    {
        return $this->result->fetchRow($fetchStyle);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssoc(): ?array
    {
        return $this->fetch(self::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchObject(): ?stdClass
    {
        return $this->fetch(self::FETCH_OBJECT);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchArray(): ?array
    {
        return $this->fetch(self::FETCH_ARRAY);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn($col, &$ref): ?bool
    {
        return $this->result->fetchCol($ref, $col);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(int $fetchStyle = self::FETCH_ASSOC): array
    {
        return $this->result->fetchAll($fetchStyle);
    }

    /**
     * {@inheritDoc}
     */
    public function isUnbuffered(): bool
    {
        return false;
    }
}
