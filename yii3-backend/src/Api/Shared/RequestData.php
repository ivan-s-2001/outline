<?php

declare(strict_types=1);

namespace App\Api\Shared;

use Psr\Http\Message\ServerRequestInterface;

final class RequestData
{
    /** @return array<string, mixed> */
    public static function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    public static function string(array $data, string $key, ?string $default = null): ?string
    {
        $value = $data[$key] ?? $default;
        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    public static function nullableString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = trim((string) $data[$key]);
        return $value === '' ? null : $value;
    }

    public static function int(array $data, string $key, int $default = 0): int
    {
        return isset($data[$key]) ? (int) $data[$key] : $default;
    }

    public static function bool(array $data, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }

        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
