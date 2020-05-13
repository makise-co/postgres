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
- [pecl-pq](https://pecl.php.net/package/pq)

## Documentation & Examples

Prepared statements and parameterized queries support named placeholders, as well as `?` and standard numeric (i.e. `$1`) placeholders.

More examples can be found in the [`examples`](examples) directory.

```php
<?php

declare(strict_types=1);

use MakiseCo\Postgres\ConnectConfigBuilder;
use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ResultSet;

use function Swoole\Coroutine\run;

run(static function () {
    $config = (new ConnectConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5432)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->withDatabase('cern')
        ->build();
    // or:
    $config = (new ConnectConfigBuilder())
        ->fromArray([
            'host' => '127.0.0.1',
            'port' => 5432,
            'user' => 'makise',
            'password' => 'el-psy-congroo',
            'database' => 'cern',
        ])
        ->build();

    $connection = new Connection($config);
    $connection->connect();

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
