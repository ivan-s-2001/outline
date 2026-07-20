<?php

declare(strict_types=1);

namespace App\Domain\Group;

use Ramsey\Uuid\Uuid;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class GroupService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $teamId, ?string $query, ?string $userId, int $offset, int $limit): array
    {
        $joins = '';
        $where = ['g.team_id = :team_id'];
        $params = [':team_id' => $teamId];
        if ($query !== null && $query !== '') {
            $where[] = 'g.name LIKE :query';
            $params[':query'] = '%' . addcslashes($query, '%_') . '%';
        }
        if ($userId !== null && $userId !== '') {
            $joins = 'INNER JOIN group_users filter_gu ON filter_gu.group_id = g.id';
            $where[] = 'filter_gu.user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        $offset = max(0, $offset);
        $limit = max(1, min(100, $limit));

        return $this->database->createCommand(
            'SELECT g.*, (SELECT COUNT(*) FROM group_users gu WHERE gu.group_id = g.id) AS member_count '
            . 'FROM groups g ' . $joins . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY g.name ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        )->queryAll();
    }

    /** @return array<string, mixed>|null */
    public function find(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT g.*, (SELECT COUNT(*) FROM group_users gu WHERE gu.group_id = g.id) AS member_count
            FROM groups g
            WHERE g.id = :id AND g.team_id = :team_id
            LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(string $teamId, string $name, ?string $externalId): array
    {
        $id = Uuid::uuid4()->toString();
        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO groups (id, team_id, name, external_id, created_at, updated_at)
            VALUES (:id, :team_id, :name, :external_id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
            SQL,
            [':id' => $id, ':team_id' => $teamId, ':name' => $name, ':external_id' => $externalId],
        )->execute();

        return $this->find($teamId, $id) ?? throw new \RuntimeException('Created group was not found.');
    }

    /** @return array<string, mixed>|null */
    public function update(string $teamId, string $id, string $name): ?array
    {
        $affected = $this->database->createCommand(
            <<<'SQL'
            UPDATE groups SET name = :name, updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id
            SQL,
            [':name' => $name, ':id' => $id, ':team_id' => $teamId],
        )->execute();

        return $affected > 0 ? $this->find($teamId, $id) : null;
    }

    public function delete(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            'DELETE FROM groups WHERE id = :id AND team_id = :team_id',
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }

    public function addUser(string $teamId, string $groupId, string $userId): bool
    {
        $valid = (int) $this->database->createCommand(
            <<<'SQL'
            SELECT COUNT(*)
            FROM groups g INNER JOIN users u ON u.team_id = g.team_id
            WHERE g.id = :group_id AND u.id = :user_id AND g.team_id = :team_id
            SQL,
            [':group_id' => $groupId, ':user_id' => $userId, ':team_id' => $teamId],
        )->queryScalar();
        if ($valid !== 1) {
            return false;
        }

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO group_users (group_id, user_id, created_at)
            VALUES (:group_id, :user_id, UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE created_at = created_at
            SQL,
            [':group_id' => $groupId, ':user_id' => $userId],
        )->execute();
        return true;
    }

    public function removeUser(string $teamId, string $groupId, string $userId): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            DELETE gu FROM group_users gu
            INNER JOIN groups g ON g.id = gu.group_id
            WHERE gu.group_id = :group_id AND gu.user_id = :user_id AND g.team_id = :team_id
            SQL,
            [':group_id' => $groupId, ':user_id' => $userId, ':team_id' => $teamId],
        )->execute() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function users(string $teamId, string $groupId): array
    {
        return $this->database->createCommand(
            <<<'SQL'
            SELECT u.*, gu.created_at AS membership_created_at
            FROM group_users gu
            INNER JOIN groups g ON g.id = gu.group_id
            INNER JOIN users u ON u.id = gu.user_id
            WHERE gu.group_id = :group_id AND g.team_id = :team_id AND u.deleted_at IS NULL
            ORDER BY u.name
            SQL,
            [':group_id' => $groupId, ':team_id' => $teamId],
        )->queryAll();
    }
}
