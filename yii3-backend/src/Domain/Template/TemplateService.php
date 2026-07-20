<?php

declare(strict_types=1);

namespace App\Domain\Template;

use Ramsey\Uuid\Uuid;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class TemplateService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $teamId): array
    {
        return $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM templates
            WHERE team_id = :team_id AND deleted_at IS NULL
            ORDER BY name
            SQL,
            [':team_id' => $teamId],
        )->queryAll();
    }

    /** @return array<string, mixed>|null */
    public function find(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM templates
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(string $teamId, string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();
        $content = $data['content'] ?? $data['data'] ?? null;
        if ($content !== null && !is_string($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO templates (
                id, team_id, name, description, title, text, content, icon,
                created_by_id, created_at, updated_at
            ) VALUES (
                :id, :team_id, :name, :description, :title, :text, :content, :icon,
                :created_by_id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
            )
            SQL,
            [
                ':id' => $id,
                ':team_id' => $teamId,
                ':name' => (string) $data['name'],
                ':description' => $data['description'] ?? null,
                ':title' => $data['title'] ?? null,
                ':text' => $data['text'] ?? '',
                ':content' => $content,
                ':icon' => $data['icon'] ?? null,
                ':created_by_id' => $userId,
            ],
        )->execute();

        return $this->find($teamId, $id) ?? throw new \RuntimeException('Created template was not found.');
    }

    /** @return array<string, mixed>|null */
    public function update(string $teamId, string $id, array $changes): ?array
    {
        if ($this->find($teamId, $id) === null) {
            return null;
        }

        $allowed = [
            'name' => 'name',
            'description' => 'description',
            'title' => 'title',
            'text' => 'text',
            'content' => 'content',
            'data' => 'content',
            'icon' => 'icon',
        ];
        $sets = [];
        $params = [':id' => $id, ':team_id' => $teamId];
        foreach ($allowed as $input => $column) {
            if (!array_key_exists($input, $changes)) {
                continue;
            }
            if ($input === 'data' && array_key_exists('content', $changes)) {
                continue;
            }
            $placeholder = ':v_' . $column;
            $sets[$column] = "`{$column}` = {$placeholder}";
            $value = $changes[$input];
            if ($column === 'content' && $value !== null && !is_string($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            $params[$placeholder] = $value;
        }

        if ($sets !== []) {
            $sets[] = 'updated_at = UTC_TIMESTAMP(6)';
            $this->database->createCommand(
                'UPDATE templates SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL',
                $params,
            )->execute();
        }

        return $this->find($teamId, $id);
    }

    public function delete(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE templates
            SET deleted_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }
}
