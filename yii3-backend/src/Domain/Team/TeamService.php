<?php

declare(strict_types=1);

namespace App\Domain\Team;

use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class TeamService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        $row = $this->database->createCommand(
            'SELECT * FROM teams WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            [':id' => $id],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(string $userId): array
    {
        return $this->database->createCommand(
            <<<'SQL'
            SELECT t.*
            FROM teams t
            INNER JOIN users u ON u.team_id = t.id
            WHERE u.id = :user_id
              AND u.deleted_at IS NULL
              AND t.deleted_at IS NULL
            ORDER BY t.name
            SQL,
            [':user_id' => $userId],
        )->queryAll();
    }

    /** @return array<string,mixed>|null */
    public function update(string $id, array $changes): ?array
    {
        if ($this->find($id) === null) {
            return null;
        }

        $allowed = [
            'name' => 'name',
            'subdomain' => 'subdomain',
            'avatarUrl' => 'avatar_url',
            'color' => 'color',
            'defaultUserRole' => 'default_user_role',
            'preferences' => 'preferences',
        ];
        $sets = [];
        $params = [':id' => $id];
        foreach ($allowed as $input => $column) {
            if (!array_key_exists($input, $changes)) {
                continue;
            }
            $placeholder = ':v_' . $column;
            $sets[] = "`{$column}` = {$placeholder}";
            $value = $changes[$input];
            if ($input === 'preferences' && !is_string($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            $params[$placeholder] = $value;
        }

        if ($sets !== []) {
            $sets[] = 'updated_at = UTC_TIMESTAMP(6)';
            $this->database->createCommand(
                'UPDATE teams SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL',
                $params,
            )->execute();
        }

        return $this->find($id);
    }

    public function delete(string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE teams
            SET deleted_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND deleted_at IS NULL
            SQL,
            [':id' => $id],
        )->execute() > 0;
    }
}
