<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class SubscriptionPresenter
{
    /** @param array<string, mixed> $row */
    public static function subscription(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'userId' => (string) $row['user_id'],
            'documentId' => $row['document_id'] ?? null,
            'collectionId' => $row['collection_id'] ?? null,
            'eventType' => (string) $row['event_type'],
            'createdAt' => $row['created_at'] ?? null,
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
