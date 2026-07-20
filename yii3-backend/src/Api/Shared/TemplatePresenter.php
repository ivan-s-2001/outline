<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class TemplatePresenter
{
    /** @param array<string, mixed> $row */
    public static function template(array $row): array
    {
        $content = $row['content'] ?? null;
        if (is_string($content) && $content !== '') {
            $content = json_decode($content, true) ?? null;
        }

        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'description' => $row['description'] ?? null,
            'title' => $row['title'] ?? null,
            'text' => $row['text'] ?? '',
            'content' => $content,
            'icon' => $row['icon'] ?? null,
            'createdById' => $row['created_by_id'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    public static function policy(string $id): array
    {
        return [
            'id' => $id,
            'abilities' => [
                'read' => true,
                'update' => true,
                'delete' => true,
                'createDocument' => true,
            ],
        ];
    }
}
