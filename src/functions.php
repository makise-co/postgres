<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Error;

use function array_map;
use function gettype;
use function implode;
use function method_exists;
use function str_replace;

/**
 * Casts a PHP value to a representation that is understood by Postgres, including encoding arrays.
 *
 * @param mixed $value
 *
 * @return string|int|float|null
 *
 * @throws Error If $value is an object without a __toString() method, a resource, or an unknown type.
 */
function cast($value)
{
    switch ($type = gettype($value)) {
        case "NULL":
        case "integer":
        case "double":
        case "string":
            return $value; // No casting necessary for numerics, strings, and null.

        case "boolean":
            return $value ? 't' : 'f';

        case "array":
            return encode($value);

        case "object":
            if (!method_exists($value, "__toString")) {
                throw new Error("Object without a __toString() method included in parameter values");
            }

            return (string)$value;

        default:
            throw new Error("Invalid value type '$type' in parameter values");
    }
}

/**
 * Encodes an array into a PostgreSQL representation of the array.
 *
 * @param array $array
 *
 * @return string The serialized representation of the array.
 *
 * @throws Error If $array contains an object without a __toString() method, a resource, or an unknown type.
 */
function encode(array $array): string
{
    $array = array_map(
        function ($value) {
            switch (gettype($value)) {
                case "NULL":
                    return "NULL";

                case "object":
                    if (!method_exists($value, "__toString")) {
                        throw new Error("Object without a __toString() method in array");
                    }

                    $value = (string)$value;
                // no break

                case "string":
                    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';

                default:
                    return cast($value); // Recursively encodes arrays and errors on invalid values.
            }
        },
        $array
    );

    return '{' . implode(',', $array) . '}';
}
