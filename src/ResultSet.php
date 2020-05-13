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

abstract class ResultSet
{
    public const DEFAULT_FETCH_STYLE = Result::FETCH_ASSOC;
    public const DEFAULT_AUTO_CONVERT = Result::CONV_SCALAR | Result::CONV_ARRAY;

    protected int $autoConvert = self::DEFAULT_AUTO_CONVERT;

    /**
     * Control which column types converted to native PHP types
     *
     * @param int $autoConvert
     */
    public function setAutoConvert(int $autoConvert): void
    {
        $this->autoConvert = $autoConvert;
    }

    /**
     * Iteratively fetch a row
     *
     * @param int $fetchStyle fetch style, one of pq\Result::FETCH_* constants
     * @return array|stdClass|null array numerically indexed for pq\Result::FETCH_ARRAY
     *         or array associatively indexed for pq\Result::FETCH_ASSOC
     *         or object stdClass instance for pq\Result::FETCH_OBJECT
     *         or NULL when iteration ends.
     */
    abstract public function fetch(int $fetchStyle = self::DEFAULT_FETCH_STYLE);

    /**
     * Iteratively fetch a row as associatively indexed array by column name
     *
     * @return array|null associatively indexed array or NULL when iteration ends.
     */
    abstract public function fetchAssoc(): ?array;

    /**
     * Iteratively fetch a row as stdClass instance, where the column names are the property names.
     *
     * @return stdClass|null instance of stdClass or NULL when iteration ends.
     */
    abstract public function fetchObject(): ?stdClass;

    /**
     * Iteratively fetch a row as numerically indexed array, where the index start with 0
     *
     * @return array|null numerically indexed array or NULL when iteration ends.
     */
    abstract public function fetchArray(): ?array;

    /**
     * Iteratively fetch a single column.
     *
     * @param int|string $col The column name or index to fetch.
     * @param mixed &$ref The variable where the column value will be stored in.
     * @return bool|null bool success or NULL when iteration ends.
     */
    abstract public function fetchCol($col, &$ref): ?bool;

    /**
     * Fetch all rows at once.
     *
     * @param int $fetchStyle fetch style, one of pq\Result::FETCH_* constants
     * @return array all fetched rows.
     */
    abstract public function fetchAll(int $fetchStyle = self::DEFAULT_FETCH_STYLE): array;

    /**
     * Fetch all rows of a single column.
     *
     * @param int|string $col The column name or index to fetch.
     * @return array list of column values.
     */
    abstract public function fetchAllCols($col): array;

    /**
     * Iteratively fetch a row into bound variables.
     *
     * @param array $map map of resultSet column bindings to variables in format: colName => &$ref
     * @return array|null array the fetched row as numerically indexed array or NULL when iteration ends.
     */
    abstract public function fetchBound(array $map = []): ?array;

    /**
     * @return int Number of fields (columns) in each result set.
     */
    abstract public function getFieldCount(): int;
}