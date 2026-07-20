<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class OutlinePresenter
{
    /** @param array<string, mixed> $row */
    public static function user(array $row): array
    {
        return [
            'id' => (string) ($row['user_id'] ?? $row['id'] ?? ''),
            'name' => (string) ($row['user_name'] ?? $row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'avatarUrl' => $row['avatar_url'] ?? null,
            'role' => (string) ($row['role'] ?? 'member'),
            'state' => (string) ($row['state'] ?? 'active'),
            'language' => (string) ($row['language'] ?? 'en_US'),
            'preferences' => self::json($row['user_preferences'] ?? $row['preferences'] ?? null, []),
            'teamId' => (string) ($row['team_id'] ?? ''),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function team(array $row): array
    {
        return [
            'id' => (string) ($row['team_id'] ?? $row['id'] ?? ''),
            'name' => (string) ($row['team_name'] ?? $row['name'] ?? ''),
            'subdomain' => $row['subdomain'] ?? null,
            'avatarUrl' => $row['team_avatar_url'] ?? $row['avatar_url'] ?? null,
            'color' => $row['team_color'] ?? $row['color'] ?? null,
            'preferences' => self::json($row['team_preferences'] ?? $row['preferences'] ?? null, []),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function collection(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'description' => $row['description'] ?? null,
            'permission' => (string) ($row['permission'] ?? 'read_write'),
            'color' => $row['color'] ?? null,
            'icon' => $row['icon'] ?? null,
            'documentStructure' => self::json($row['document_structure'] ?? null, []),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
            'archivedAt' => $row['archived_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function document(array $row): array
    {
        $title = (string) ($row['title'] ?? '');
        $urlId = (string) ($row['url_id'] ?? '');

        return [
            'id' => (string) $row['id'],
            'urlId' => $urlId,
            'url' => '/' . self::slug($title) . '-' . $urlId,
            'title' => $title,
            'text' => $row['text'] ?? '',
            'content' => self::json($row['content'] ?? null, null),
            'collectionId' => $row['collection_id'] ?? null,
            'parentDocumentId' => $row['parent_document_id'] ?? null,
            'templateId' => $row['template_id'] ?? null,
            'icon' => $row['icon'] ?? null,
            'color' => $row['color'] ?? null,
            'createdById' => $row['created_by_id'] ?? null,
            'updatedById' => $row['updated_by_id'] ?? null,
            'revision' => (int) ($row['revision_number'] ?? 0),
            'publishedAt' => $row['published_at'] ?? null,
            'archivedAt' => $row['archived_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private static function json(mixed $value, mixed $default): mixed
    {
        if (is_array($value) || is_object($value) || $value === null) {
            return $value ?? $default;
        }

        if (!is_string($value) || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    private static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? mb_substr($value, 0, 80) : 'doc';
    }
}
