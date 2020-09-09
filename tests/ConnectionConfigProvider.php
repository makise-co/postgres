<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\ConnectionConfig;

class ConnectionConfigProvider
{
    private static ?ConnectionConfig $config = null;

    public static function getConfig(): ConnectionConfig
    {
        if (self::$config !== null) {
            return self::$config;
        }

        return self::$config = new ConnectionConfig(
            'host.docker.internal',
            5432,
            'makise',
            'el-psy-congroo',
            'makise',
        );
    }

    public static function getString(): string
    {
        return 'host=host.docker.internal user=makise password=el-psy-congroo dbname=makise';
    }
}
