<?php

declare(strict_types=1);

use Yiisoft\Db\Mysql\Dsn;

$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);
    if ($value !== false) {
        return (string) $value;
    }

    return isset($_ENV[$name]) ? (string) $_ENV[$name] : $default;
};

return [
    'application' => require __DIR__ . '/application.php',

    'yiisoft/aliases' => [
        'aliases' => require __DIR__ . '/aliases.php',
    ],

    'yiisoft/db-mysql' => [
        'dsn' => new Dsn(
            $env('DB_DRIVER', 'mysql'),
            $env('DB_HOST', 'MariaDB-11.8'),
            $env('DB_NAME', 'outline_yii3'),
            $env('DB_PORT', '3306'),
            ['charset' => $env('DB_CHARSET', 'utf8mb4')],
        ),
        'username' => $env('DB_USER', 'root'),
        'password' => $env('DB_PASSWORD'),
        'tablePrefix' => $env('DB_TABLE_PREFIX'),
    ],

    'redis' => [
        'host' => $env('REDIS_HOST', 'Redis'),
        'port' => (int) $env('REDIS_PORT', '6379'),
        'password' => $env('REDIS_PASSWORD'),
        'database' => (int) $env('REDIS_DATABASE', '0'),
    ],
];
