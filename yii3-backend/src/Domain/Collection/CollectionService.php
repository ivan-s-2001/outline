<?php

declare(strict_types=1);

namespace App\Domain\Collection;

use Ramsey\Uuid\Uuid;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class CollectionService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $teamId, bool $includeArchived = false): array
    {
        $archiveCondition = $includeArchived ? '' : 'AND archived_at IS NULL';

        return $this->database->createCommand(
            <<<SQL
            SELECT *
            FROM collections
            WHERE team_id = :team_id
              AND deleted_at IS NULL
              {$archiveCondition}
            ORDER BY name ASC
            SQL,
            [':team_id' => $teamId],
        )->queryAll();
    }

    /** @return array<string, mixed>|null */
    public function find(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT *
            FROM collections
            WHERE id = :id
              AND team_id = :team_id
              AND deleted_at IS NULL
            LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(
        string $teamId,
        string $userId,
        string $name,
        ?string $description,
        string $permission,
        ?string $color,
        ?string $icon,
    ): array {
        $id = Uuid::uuid4()->toString();

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO collections (
                id, team_id, name, description, permission, color, icon,
                document_structure, created_by_id, created_at, updated_at
            ) VALUES (
                :id, :team_id, :name, :description, :permission, :color, :icon,
                '[]', :created_by_id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
            )
            SQL,
            [
                ':id' => $id,
                ':team_id' => $teamId,
                ':name' => $name,
                ':description' => $description,
                ':permission' => $permission,
                ':color' => $color,
                ':icon' => $icon,
                ':created_by_id' => $userId,
            ],
        )->execute();

        return $this->find($teamId, $id) ?? throw new \RuntimeException('Created collection was not found.');
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
            'permission' => 'permission',
            'color' => 'color',
            'icon' => 'icon',
            'documentStructure' => 'document_structure',
        ];
        $sets = [];
        $params = [':id' => $id, ':team_id' => $teamId];

        foreach ($allowed as $input => $column) {
            if (!array_key_exists($input, $changes)) {
                continue;
            }
            $placeholder = ':v_' . $column;
            $sets[] = "`{$column}` = {$placeholder}";
            $value = $changes[$input];
            if ($input === 'documentStructure' && !is_string($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            $params[$placeholder] = $value;
        }

        if ($sets !== []) {
            $sets[] = 'updated_at = UTC_TIMESTAMP(6)';
            $sql = 'UPDATE collections SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL';
            $this->database->createCommand($sql, $params)->execute();
        }

        return $this->find($teamId, $id);
    }

    public function archive(string $teamId, string $id, bool $archived): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE collections
            SET archived_at = :archived_at, updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            SQL,
            [
                ':archived_at' => $archived ? gmdate('Y-m-d H:i:s.u') : null,
                ':id' => $id,
                ':team_id' => $teamId,
            ],
        )->execute() > 0;
    }

    public function delete(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE collections
            SET deleted_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }
}
