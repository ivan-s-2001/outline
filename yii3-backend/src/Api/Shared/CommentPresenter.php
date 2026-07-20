<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class CommentPresenter
{
    /** @param array<string, mixed> $row */
    public static function comment(array $row): array
    {
        $data = $row['data'] ?? null;
        if (is_string($data)) {
            $data = json_decode($data, true) ?? ['text' => $data];
        }

        return [
            'id' => (string) $row['id'],
            'documentId' => (string) $row['document_id'],
            'userId' => $row['user_id'] ?? null,
            'parentCommentId' => $row['parent_comment_id'] ?? null,
            'data' => $data,
            'resolvedAt' => $row['resolved_at'] ?? null,
            'resolvedById' => $row['resolved_by_id'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function reaction(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'commentId' => (string) $row['comment_id'],
            'userId' => (string) $row['user_id'],
            'emoji' => (string) $row['emoji'],
            'createdAt' => $row['created_at'] ?? null,
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
                'resolve' => true,
                'createReaction' => true,
            ],
        ];
    }
}
