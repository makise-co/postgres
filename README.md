# postgres-client
A Pure PHP coroutine client for PostgreSQL based on libpq

Inspired by [amphp/postgres](https://github.com/amphp/postgres)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require makise-co/postgres
```

## Requirements

- PHP 7.4+
- Swoole 4.4+
- [ext-pq](https://pecl.php.net/package/pq) for `PqConnection`
- ext-pgsql for `PgSqlConnection`

## Supported underlying drivers
* Pq based on ext-pq
* PgSql based on ext-pgsql

\* The PgSql driver has a poor performance (at least 20% slower than PDO)

## Benchmarks

| Driver          	| Try 1    	| Try 2    	| Try 3    	| Sum      	| Performance vs PDO 	|
|-----------------	|----------	|----------	|----------	|----------	|--------------------	|
| PDO PGSQL       	| 0.631116 	| 0.629111 	| 0.639473 	| 1.899700 	| -                  	|
| Pq (buffered)   	| 0.696158 	| 0.708033 	| 0.703638 	| 2.107829 	| -9.8741%           	|
| Pq (unbuffered) 	| 1.517776 	| 1.289702 	| 1.355651 	| 4.163129 	| -54.3685%          	|
| PgSql           	| 0.918096 	| 0.918936 	| 0.918936 	| 2.755968 	| -31.0696%          	|
| Swoole* (raw)    	| 0.600656 	| 0.553807 	| 0.594692 	| 1.749155 	| +8.6067%           	|
| Pq (raw)        	| 0.626909 	| 0.632697 	| 0.629343 	| 1.888949 	| +0.5692%           	|
| PgSql (raw)     	| 0.561219 	| 0.561540 	| 0.571930 	| 1.694689 	| +12.0973%          	|

The asterisk mark means that the driver is not implemented yet.

\* The PgSql (raw) is so fast in this benchmark because there is no code to convert results to native PHP types.

All benchmarks can be found in the [`benchmark`](benchmark) directory.


## Documentation & Examples

Prepared statements and parameterized queries support named placeholders, as well as `?` and standard numeric (i.e. `$1`) placeholders.

More examples can be found in the [`examples`](examples) directory.

```php
<?php

declare(strict_types=1);

use MakiseCo\Postgres\ConnectionConfigBuilder;
use MakiseCo\Postgres\Driver\Pq\PqConnection;
use MakiseCo\Postgres\Driver\PgSql\PgSqlConnection;
use MakiseCo\SqlCommon\Contracts\ResultSet;

use function Swoole\Coroutine\run;

run(static function () {
    $config = (new ConnectionConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5432)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->withDatabase('cern')
        ->build();
    // or:
    $config = (new ConnectionConfigBuilder())
        ->fromArray([
            'host' => '127.0.0.1',
            'port' => 5432,
            'user' => 'makise',
            'password' => 'el-psy-congroo',
            'database' => 'cern',
        ])
        ->build();

    $connection = PqConnection::connect($config);
    // or
    $connection = PgSqlConnection::connect($config);

    $statement = $connection->prepare("SELECT * FROM test WHERE id = :id");

    /** @var ResultSet $result */
    $result = $statement->execute(['id' => 1337]);

    while ($row = $result->fetchAssoc()) {
        // $row is an array (map) of column values. e.g.: $row['column_name']
    }
});
```

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
