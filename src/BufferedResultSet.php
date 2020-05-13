<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use pq\Result;
use stdClass;

final class BufferedResultSet extends ResultSet
{
    /** @var Result */
    private Result $result;

    private int $numCols;

    /**
     * @param Result $result PostgreSQL result object.
     */
    public function __construct(Result $result)
    {
        $this->result = $result;
        $this->numCols = $result->numCols;
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(int $fetchStyle = self::DEFAULT_FETCH_STYLE)
    {
        $this->result->autoConvert = $this->autoConvert;

        return $this->result->fetchRow($fetchStyle);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssoc(): ?array
    {
        return $this->fetch(Result::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchObject(): ?stdClass
    {
        return $this->fetch(Result::FETCH_OBJECT);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchArray(): ?array
    {
        return $this->fetch(Result::FETCH_ARRAY);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchCol($col, &$ref): ?bool
    {
        $this->result->autoConvert = $this->autoConvert;

        return $this->result->fetchCol($ref, $col);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(int $fetchStyle = self::DEFAULT_FETCH_STYLE): array
    {
        $this->result->autoConvert = $this->autoConvert;

        return $this->result->fetchAll($fetchStyle);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllCols($col): array
    {
        $this->result->autoConvert = $this->autoConvert;

        return $this->result->fetchAllCols($col);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchBound(array $map = []): ?array
    {
        /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
        foreach ($map as $col => &$property) {
            $this->result->bind($col, $property);
        }

        return $this->result->fetchBound();
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldCount(): int
    {
        return $this->numCols;
    }
}
