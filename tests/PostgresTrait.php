<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfig;

use function getenv;

trait PostgresTrait
{
    protected function getConnectConfig(
        float $connectTimeout = 2,
        bool $unbuffered = true,
        ?string $user = null
    ): ConnectionConfig {
        $host = getenv('POSTGRES_HOST');
        if (!$host) {
            $host = 'postgres';
        }

        $user = $user ?? getenv('POSTGRES_USER');
        if (!$user) {
            $user = 'postgres';
        }

        $password = getenv('POSTGRES_PASSWORD');
        if (!$password) {
            $password = 'secret';
        }

        $database = getenv('POSTGRES_DATABASE');
        if (!$database) {
            $database = 'makise';
        }

        return new ConnectionConfig(
            $host,
            5432,
            $user,
            $password,
            $database,
            [
                'application_name' => 'Makise Postgres Client',
                'client_encoding' => 'utf8',
                'options' => '-c search_path=public -c timezone=UTC'
            ],
            $connectTimeout,
            $unbuffered
        );
    }

    protected function getConnection(?ConnectionConfig $config = null): Connection
    {
        if (null === $config) {
            $config = $this->getConnectConfig();
        }

        $connection = new Connection($config);
        $connection->connect();

        return $connection;
    }

    /**
     * @return array Start test data for database.
     */
    public function getData(): array
    {
        return [
            ['amphp', 'org'],
            ['github', 'com'],
            ['google', 'com'],
            ['php', 'net'],
        ];
    }
}
