<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class SharePresenter
{
    /** @param array<string, mixed> $row */
    public static function share(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'urlId' => (string) $row['url_id'],
            'url' => '/share/' . $row['url_id'],
            'documentId' => $row['document_id'] ?? null,
            'collectionId' => $row['collection_id'] ?? null,
            'published' => (bool) $row['published'],
            'includeChildDocuments' => (bool) $row['include_child_documents'],
            'allowIndexing' => (bool) $row['allow_indexing'],
            'createdById' => $row['created_by_id'] ?? null,
            'lastAccessedAt' => $row['last_accessed_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
            'revokedAt' => $row['revoked_at'] ?? null,
        ];
    }

    public static function policy(string $id, bool $write): array
    {
        return [
            'id' => $id,
            'abilities' => [
                'read' => true,
                'update' => $write,
                'delete' => $write,
                'revoke' => $write,
            ],
        ];
    }
}
