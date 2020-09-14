<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\PgSql;

use MakiseCo\Postgres\Exception\ParseException;
use MakiseCo\Postgres\Internal\ArrayParser;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Exception\FailureException;
use stdClass;

use function array_key_exists;
use function is_string;
use function pg_fetch_array;
use function pg_fetch_assoc;
use function pg_fetch_object;
use function pg_field_name;
use function pg_field_type_oid;
use function pg_free_result;
use function pg_num_fields;
use function pg_numrows;
use function pg_result_error;

use const PGSQL_BOTH;
use const PGSQL_NUM;

class PgSqlResultSet implements ResultSet
{
    /** @var resource PostgreSQL result resource. */
    private $handle;

    private int $position = 0;

    private int $numRows;
    private int $numFields;

    /** @var int[] */
    private array $fieldTypes = [];

    /** @var string[] */
    private array $fieldNames = [];

    /** @var ArrayParser */
    private ArrayParser $parser;

    /**
     * @param resource $handle PostgreSQL result resource.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;

        $this->numRows = pg_numrows($handle);

        $this->numFields = $numFields = pg_num_fields($this->handle);
        for ($i = 0; $i < $numFields; ++$i) {
            $this->fieldNames[] = pg_field_name($this->handle, $i);
            $this->fieldTypes[] = pg_field_type_oid($this->handle, $i);
        }

        $this->parser = new ArrayParser();
    }

    public function __destruct()
    {
        pg_free_result($this->handle);
    }

    public function getNumRows(): int
    {
        return $this->numRows;
    }

    /**
     * @inheritDoc
     */
    public function getFieldCount(): int
    {
        return $this->numFields;
    }

    /**
     * @inheritDoc
     */
    public function fetch(int $fetchStyle = self::FETCH_ASSOC)
    {
        // no more rows available
        if ($this->position >= $this->numRows) {
            return null;
        }

        $this->position++;

        switch ($fetchStyle) {
            case self::FETCH_ARRAY:
                $result = pg_fetch_array($this->handle, null, PGSQL_NUM);
                break;
            case self::FETCH_OBJECT:
                $result = pg_fetch_object($this->handle);
                break;
            // assoc is default
            default:
                $result = pg_fetch_assoc($this->handle);
                break;
        }

        if ($result === false) {
            $message = pg_result_error($this->handle);
            pg_free_result($this->handle);

            throw new FailureException($message);
        }

        if ($fetchStyle === self::FETCH_ARRAY || $fetchStyle === self::FETCH_ASSOC) {
            $column = 0;

            foreach ($result as $key => $value) {
                if ($value !== null) {
                    $result[$key] = $this->cast($column, $value);
                }

                $column++;
            }
        } elseif ($fetchStyle === self::FETCH_OBJECT) {
            $column = 0;

            foreach ($result as $key => $value) {
                if ($value !== null) {
                    $result->{$key} = $this->cast($column, $value);
                }

                $column++;
            }
        }

        return $result;
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
     * @inheritDoc
     */
    public function fetchColumn($col, &$ref): ?bool
    {
        // no more rows available
        if ($this->position >= $this->numRows) {
            return null;
        }

        $this->position++;

        $result = pg_fetch_array($this->handle, null, PGSQL_BOTH);
        if (false === $result) {
            $message = pg_result_error($this->handle);
            pg_free_result($this->handle);

            throw new FailureException($message);
        }

        // column not found in result set
        if (!array_key_exists($col, $result)) {
            return false;
        }

        if (is_string($col)) {
            $columnNum = null;

            // find column num by name
            foreach ($this->fieldNames as $num => $fieldName) {
                if ($fieldName === $col) {
                    $columnNum = $num;
                    break;
                }
            }

            if ($columnNum === null) {
                return false;
            }
        } else {
            $columnNum = $col;
        }

        $ref = $this->cast($columnNum, $result[$col]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(int $fetchStyle = self::FETCH_ASSOC): array
    {
        $rows = [];

        while ($row = $this->fetch($fetchStyle)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @inheritDoc
     */
    public function isUnbuffered(): bool
    {
        return true;
    }

    /**
     * @see https://github.com/postgres/postgres/blob/REL_10_STABLE/src/include/catalog/pg_type.h for OID types.
     *
     * @param int $column
     * @param string $value
     *
     * @return array|bool|float|int Cast value.
     *
     * @throws ParseException
     */
    private function cast(int $column, string $value)
    {
        switch ($this->fieldTypes[$column]) {
            case 16: // bool
                return $value === 't';

            case 20: // int8
            case 21: // int2
            case 23: // int4
            case 26: // oid
            case 27: // tid
            case 28: // xid
                return (int)$value;

            case 700: // real
            case 701: // double-precision
                return (float)$value;

            case 1000: // boolean[]
                return $this->parser->parse(
                    $value,
                    static function (string $value): bool {
                        return $value === 't';
                    }
                );

            case 1005: // int2[]
            case 1007: // int4[]
            case 1010: // tid[]
            case 1011: // xid[]
            case 1016: // int8[]
            case 1028: // oid[]
                return $this->parser->parse(
                    $value,
                    static function (string $value): int {
                        return (int)$value;
                    }
                );

            case 1021: // real[]
            case 1022: // double-precision[]
                return $this->parser->parse(
                    $value,
                    static function (string $value): float {
                        return (float)$value;
                    }
                );

            case 1020: // box[] (semi-colon delimited)
                return $this->parser->parse($value, null, ';');

            case 199:  // json[]
            case 629:  // line[]
            case 651:  // cidr[]
            case 719:  // circle[]
            case 775:  // macaddr8[]
            case 791:  // money[]
            case 1001: // bytea[]
            case 1002: // char[]
            case 1003: // name[]
            case 1006: // int2vector[]
            case 1008: // regproc[]
            case 1009: // text[]
            case 1013: // oidvector[]
            case 1014: // bpchar[]
            case 1015: // varchar[]
            case 1019: // path[]
            case 1023: // abstime[]
            case 1024: // realtime[]
            case 1025: // tinterval[]
            case 1027: // polygon[]
            case 1034: // aclitem[]
            case 1040: // macaddr[]
            case 1041: // inet[]
            case 1115: // timestamp[]
            case 1182: // date[]
            case 1183: // time[]
            case 1185: // timestampz[]
            case 1187: // interval[]
            case 1231: // numeric[]
            case 1263: // cstring[]
            case 1270: // timetz[]
            case 1561: // bit[]
            case 1563: // varbit[]
            case 2201: // refcursor[]
            case 2207: // regprocedure[]
            case 2208: // regoper[]
            case 2209: // regoperator[]
            case 2210: // regclass[]
            case 2211: // regtype[]
            case 2949: // txid_snapshot[]
            case 2951: // uuid[]
            case 3221: // pg_lsn[]
            case 3643: // tsvector[]
            case 3644: // gtsvector[]
            case 3645: // tsquery[]
            case 3735: // regconfig[]
            case 3770: // regdictionary[]
            case 3807: // jsonb[]
            case 3905: // int4range[]
            case 3907: // numrange[]
            case 3909: // tsrange[]
            case 3911: // tstzrange[]
            case 3913: // daterange[]
            case 3927: // int8range[]
            case 4090: // regnamespace[]
            case 4097: // regrole[]
                return $this->parser->parse($value);

            default:
                return $value;
        }
    }
}
