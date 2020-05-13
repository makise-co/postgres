<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\ConnectConfig;
use MakiseCo\Postgres\Connection;

use function getenv;

trait PostgresTrait
{
    protected function getConnectConfig(
        float $connectTimeout = 2,
        bool $unbuffered = true,
        ?string $user = null
    ): ConnectConfig {
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

        return new ConnectConfig(
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

    protected function getConnection(?ConnectConfig $config = null): Connection
    {
        if (null === $config) {
            $config = $this->getConnectConfig();
        }

        $connection = new Connection($config);
        $connection->connect();

        return $connection;
    }
}