<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class AttachmentPresenter
{
    /** @param array<string, mixed> $row */
    public static function attachment(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'contentType' => (string) $row['content_type'],
            'size' => (int) $row['size'],
            'acl' => (string) $row['acl'],
            'documentId' => $row['document_id'] ?? null,
            'userId' => $row['user_id'] ?? null,
            'teamId' => (string) $row['team_id'],
            'url' => '/api/attachments.redirect?id=' . rawurlencode((string) $row['id']),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    public static function policy(string $id, bool $owner, bool $admin): array
    {
        return [
            'id' => $id,
            'abilities' => [
                'read' => true,
                'update' => $owner || $admin,
                'delete' => $owner || $admin,
            ],
        ];
    }
}
