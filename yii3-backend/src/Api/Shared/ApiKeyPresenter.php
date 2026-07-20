<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class ApiKeyPresenter
{
    /** @param array<string, mixed> $row */
    public static function apiKey(array $row): array
    {
        $scope = $row['scope'] ?? null;
        if (is_string($scope) && $scope !== '') {
            $scope = json_decode($scope, true) ?? null;
        }

        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'userId' => (string) $row['user_id'],
            'scope' => $scope,
            'last4' => (string) ($row['last4'] ?? ''),
            'lastActiveAt' => $row['last_active_at'] ?? null,
            'expiresAt' => $row['expires_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'revokedAt' => $row['revoked_at'] ?? null,
        ];
    }

    public static function policy(string $id): array
    {
        return [
            'id' => $id,
            'abilities' => [
                'read' => true,
                'delete' => true,
            ],
        ];
    }
}
