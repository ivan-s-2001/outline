<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class GroupPresenter
{
    /** @param array<string, mixed> $row */
    public static function group(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'externalId' => $row['external_id'] ?? null,
            'memberCount' => (int) ($row['member_count'] ?? 0),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    public static function policy(string $id, bool $admin): array
    {
        return [
            'id' => $id,
            'abilities' => [
                'read' => true,
                'update' => $admin,
                'delete' => $admin,
                'manageUsers' => $admin,
            ],
        ];
    }
}
