<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class MembershipPresenter
{
    /** @param array<string,mixed> $row */
    public static function user(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'userId' => (string) $row['user_id'],
            'collectionId' => $row['collection_id'] ?? null,
            'documentId' => $row['document_id'] ?? null,
            'permission' => (string) $row['permission'],
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $row */
    public static function group(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'groupId' => (string) $row['group_id'],
            'collectionId' => $row['collection_id'] ?? null,
            'documentId' => $row['document_id'] ?? null,
            'permission' => (string) $row['permission'],
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
            ],
        ];
    }
}
