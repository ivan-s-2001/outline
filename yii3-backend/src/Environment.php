<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class Environment
{
    public const DEV = 'dev';
    public const TEST = 'test';
    public const PROD = 'prod';

    private const ENVIRONMENTS = [self::DEV, self::TEST, self::PROD];

    private static array $values = [];

    public static function prepare(): void
    {
        $environment = self::raw('APP_ENV') ?: self::PROD;
        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new RuntimeException(sprintf('Invalid APP_ENV "%s".', $environment));
        }

        self::$values['APP_ENV'] = $environment;
        self::$values['APP_DEBUG'] = filter_var(
            self::raw('APP_DEBUG') ?: false,
            FILTER_VALIDATE_BOOLEAN,
        );

        $hostPath = self::raw('APP_HOST_PATH');
        self::$values['APP_HOST_PATH'] = $hostPath === null || $hostPath === '' ? null : $hostPath;
    }

    public static function appEnv(): string
    {
        return self::$values['APP_ENV'];
    }

    public static function appDebug(): bool
    {
        return self::$values['APP_DEBUG'];
    }

    public static function appHostPath(): ?string
    {
        return self::$values['APP_HOST_PATH'];
    }

    private static function raw(string $key): ?string
    {
        $value = getenv($key, true);
        if ($value !== false) {
            return $value;
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return isset($_ENV[$key]) ? (string) $_ENV[$key] : null;
    }
}
