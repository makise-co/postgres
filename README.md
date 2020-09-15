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
- [ext-swoole_postgresql](https://github.com/swoole/ext-postgresql) for `SwooleConnection`

## Supported underlying drivers

| Driver 	| Buffered Mode 	| Unbuffered Mode 	| Listen 	|
|--------	|---------------	|-----------------	|--------	|
| Pq     	| Yes           	| Yes             	| Yes    	|
| PgSql  	| Yes           	| No              	| Yes    	|
| Swoole 	| Yes           	| No              	| No     	|

\* The PgSql driver has a poor performance (at least 20% slower than PDO)

## Benchmarks

| Driver          	| Try 1    	| Try 2    	| Try 3    	| Sum      	| Performance vs PDO 	|
|-----------------	|----------	|----------	|----------	|----------	|--------------------	|
| PDO PGSQL (raw)  	| 0.594816 	| 0.588032 	| 0.597217 	| 1.780065 	| -                  	|
| Pq (buffered)   	| 0.670369 	| 0.679673 	| 0.691890 	| 2.041932 	| -12.8245%          	|
| Pq (unbuffered) 	| 1.202617 	| 1.248877 	| 1.204900 	| 3.656394 	| -51.3164%          	|
| PgSql           	| 0.888312 	| 0.886494 	| 0.890477 	| 2.665283 	| -33.2129%          	|
| Swoole          	| 0.656156 	| 0.654318 	| 0.667154 	| 1.977628 	| -9.9899%           	|
| Swoole (raw)    	| 0.576667 	| 0.589855 	| 0.578105 	| 1.744627 	| +2.0313%           	|
| Pq (raw)        	| 0.643230 	| 0.645116 	| 0.656374 	| 1.944720 	| -8.4668%           	|
| PgSql (raw)     	| 0.576180 	| 0.583124 	| 0.577715 	| 1.737019 	| +2.4782%           	|

\* (raw) means that is raw driver benchmark (without any abstractions and PHP code execution).

\* The PgSql (raw) is much faster in this benchmark because there is no code to convert results to native PHP types.

All benchmarks can be found in the [`benchmark`](benchmark) directory.


## Documentation & Examples

Prepared statements and parameterized queries support named placeholders, as well as `?` and standard numeric (i.e. `$1`) placeholders.

More examples can be found in the [`examples`](examples) directory.

```php
<?php

declare(strict_types=1);

use MakiseCo\Postgres\ConnectionConfig;
use MakiseCo\Postgres\ConnectionConfigBuilder;
use MakiseCo\Postgres\Driver\Pq\PqConnection;
use MakiseCo\Postgres\Driver\PgSql\PgSqlConnection;
use MakiseCo\Postgres\Driver\Swoole\SwooleConnection;
use MakiseCo\SqlCommon\Contracts\ResultSet;

use function Swoole\Coroutine\run;

run(static function () {
    $config = (new ConnectionConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5432)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->withDatabase('cern')
        ->withSslMode('prefer')
        ->withEncoding('utf-8')
        ->withApplicationName('Makise Postgres Driver')
        ->withSearchPath(['public'])
        ->withTimezone('UTC')
        ->withConnectTimeout(1.0) // wait 1 second
        ->build();
    // or:
    $config = (new ConnectionConfigBuilder())
        ->fromArray([
            'host' => '127.0.0.1',
            'port' => 5432,
            'user' => 'makise',
            'password' => 'el-psy-congroo',
            'database' => 'cern',
            'sslmode' => 'prefer',
            'client_encoding' => 'utf-8',
            // or
            'encoding' => 'utf-8',
            // or
            'charset' => 'utf-8',
            'application_name' => 'Makise Postgres Driver',
            'search_path' => 'public', // array of strings can be passed
            // or
            'schema' => 'public', // array of strings can be passed
            'timezone' => 'UTC',
            'connect_timeout' => 1.0,
        ])
        ->build();
    // or
    $config = new ConnectionConfig(
        '127.0.0.1',
        5432,
        'makise',
        'el-psy-congroo',
        'makise',
        [
            'sslmode' => 'prefer',
            'client_encoding' => 'utf-8',
            'application_name' => 'Makise Postgres Driver',
            'options' => '-csearch_path=public -ctimezone=UTC',
        ],
        1.0,
    );

    $connection = PqConnection::connect($config);
    // or
    $connection = PgSqlConnection::connect($config);
    // or
    $connection = SwooleConnection::connect($config);

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
