<?php

declare(strict_types=1);

namespace App\Domain\User;

use Ramsey\Uuid\Uuid;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class UserService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $teamId, ?string $query, int $offset, int $limit, bool $includeSuspended): array
    {
        $where = ['team_id = :team_id', 'deleted_at IS NULL'];
        $params = [':team_id' => $teamId];
        if (!$includeSuspended) {
            $where[] = 'suspended_at IS NULL';
        }
        if ($query !== null && $query !== '') {
            $where[] = '(name LIKE :query OR email LIKE :query)';
            $params[':query'] = '%' . addcslashes($query, '%_') . '%';
        }
        $offset = max(0, $offset);
        $limit = max(1, min(100, $limit));

        return $this->database->createCommand(
            'SELECT * FROM users WHERE ' . implode(' AND ', $where)
            . ' ORDER BY name ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        )->queryAll();
    }

    /** @return array<string, mixed>|null */
    public function find(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM users
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(string $teamId, string $name, string $email, string $role): array
    {
        $id = Uuid::uuid4()->toString();
        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO users (
                id, team_id, email, name, role, state, language, preferences, created_at, updated_at
            ) VALUES (
                :id, :team_id, :email, :name, :role, 'active', 'ru_RU', '{}', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
            )
            SQL,
            [
                ':id' => $id,
                ':team_id' => $teamId,
                ':email' => mb_strtolower($email),
                ':name' => $name,
                ':role' => $role,
            ],
        )->execute();

        return $this->find($teamId, $id) ?? throw new \RuntimeException('Created user was not found.');
    }

    /** @return array<string, mixed>|null */
    public function update(string $teamId, string $id, array $changes, bool $allowRole): ?array
    {
        if ($this->find($teamId, $id) === null) {
            return null;
        }

        $allowed = [
            'name' => 'name',
            'avatarUrl' => 'avatar_url',
            'language' => 'language',
            'preferences' => 'preferences',
        ];
        if ($allowRole) {
            $allowed['role'] = 'role';
            $allowed['state'] = 'state';
        }

        $sets = [];
        $params = [':id' => $id, ':team_id' => $teamId];
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
                'UPDATE users SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL',
                $params,
            )->execute();
        }

        return $this->find($teamId, $id);
    }

    public function suspend(string $teamId, string $id, bool $suspended): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE users
            SET suspended_at = :suspended_at,
                state = :state,
                updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            SQL,
            [
                ':suspended_at' => $suspended ? gmdate('Y-m-d H:i:s.u') : null,
                ':state' => $suspended ? 'suspended' : 'active',
                ':id' => $id,
                ':team_id' => $teamId,
            ],
        )->execute() > 0;
    }

    public function delete(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE users
            SET deleted_at = UTC_TIMESTAMP(6), state = 'deleted', updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }
}
