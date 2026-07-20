<?php

declare(strict_types=1);

namespace App\Domain\Membership;

use Ramsey\Uuid\Uuid;
use RuntimeException;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class MembershipService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string,mixed>> */
    public function listUsers(string $teamId, ?string $collectionId, ?string $documentId, ?string $userId): array
    {
        $where = [];
        $params = [':team_id' => $teamId];
        if ($collectionId !== null) {
            $where[] = 'm.collection_id = :collection_id';
            $params[':collection_id'] = $collectionId;
        }
        if ($documentId !== null) {
            $where[] = 'm.document_id = :document_id';
            $params[':document_id'] = $documentId;
        }
        if ($userId !== null) {
            $where[] = 'm.user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if ($where === []) {
            throw new RuntimeException('A membership target is required.');
        }

        return $this->database->createCommand(
            'SELECT m.*, u.name AS user_name, u.email AS user_email, u.avatar_url AS user_avatar_url, u.role AS user_role, u.state AS user_state '
            . 'FROM user_memberships m '
            . 'INNER JOIN users u ON u.id = m.user_id '
            . 'LEFT JOIN collections c ON c.id = m.collection_id '
            . 'LEFT JOIN documents d ON d.id = m.document_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' AND u.team_id = :team_id '
            . 'AND (m.collection_id IS NULL OR c.team_id = :team_id) '
            . 'AND (m.document_id IS NULL OR d.team_id = :team_id) '
            . 'ORDER BY u.name',
            $params,
        )->queryAll();
    }

    /** @return list<array<string,mixed>> */
    public function listGroups(string $teamId, ?string $collectionId, ?string $documentId, ?string $groupId): array
    {
        $where = [];
        $params = [':team_id' => $teamId];
        if ($collectionId !== null) {
            $where[] = 'm.collection_id = :collection_id';
            $params[':collection_id'] = $collectionId;
        }
        if ($documentId !== null) {
            $where[] = 'm.document_id = :document_id';
            $params[':document_id'] = $documentId;
        }
        if ($groupId !== null) {
            $where[] = 'm.group_id = :group_id';
            $params[':group_id'] = $groupId;
        }
        if ($where === []) {
            throw new RuntimeException('A membership target is required.');
        }

        return $this->database->createCommand(
            'SELECT m.*, g.name AS group_name, g.external_id AS group_external_id '
            . 'FROM group_memberships m '
            . 'INNER JOIN groups g ON g.id = m.group_id '
            . 'LEFT JOIN collections c ON c.id = m.collection_id '
            . 'LEFT JOIN documents d ON d.id = m.document_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' AND g.team_id = :team_id '
            . 'AND (m.collection_id IS NULL OR c.team_id = :team_id) '
            . 'AND (m.document_id IS NULL OR d.team_id = :team_id) '
            . 'ORDER BY g.name',
            $params,
        )->queryAll();
    }

    /** @return array<string,mixed> */
    public function createUser(
        string $teamId,
        string $userId,
        ?string $collectionId,
        ?string $documentId,
        string $permission,
    ): array {
        $this->assertTarget($teamId, $collectionId, $documentId);
        $this->assertEntity('users', $teamId, $userId);
        $id = Uuid::uuid4()->toString();

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO user_memberships (
                id, user_id, collection_id, document_id, permission, created_at, updated_at
            ) VALUES (
                :id, :user_id, :collection_id, :document_id, :permission, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
            )
            ON DUPLICATE KEY UPDATE permission = VALUES(permission), updated_at = UTC_TIMESTAMP(6)
            SQL,
            [
                ':id' => $id,
                ':user_id' => $userId,
                ':collection_id' => $collectionId,
                ':document_id' => $documentId,
                ':permission' => $permission,
            ],
        )->execute();

        $row = $this->findUser($teamId, $userId, $collectionId, $documentId);
        return $row ?? throw new RuntimeException('Created user membership was not found.');
    }

    /** @return array<string,mixed> */
    public function createGroup(
        string $teamId,
        string $groupId,
        ?string $collectionId,
        ?string $documentId,
        string $permission,
    ): array {
        $this->assertTarget($teamId, $collectionId, $documentId);
        $this->assertEntity('groups', $teamId, $groupId);
        $id = Uuid::uuid4()->toString();

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO group_memberships (
                id, group_id, collection_id, document_id, permission, created_at, updated_at
            ) VALUES (
                :id, :group_id, :collection_id, :document_id, :permission, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
            )
            ON DUPLICATE KEY UPDATE permission = VALUES(permission), updated_at = UTC_TIMESTAMP(6)
            SQL,
            [
                ':id' => $id,
                ':group_id' => $groupId,
                ':collection_id' => $collectionId,
                ':document_id' => $documentId,
                ':permission' => $permission,
            ],
        )->execute();

        $row = $this->findGroup($teamId, $groupId, $collectionId, $documentId);
        return $row ?? throw new RuntimeException('Created group membership was not found.');
    }

    /** @return array<string,mixed>|null */
    public function updateUser(string $teamId, string $id, string $permission): ?array
    {
        $affected = $this->database->createCommand(
            <<<'SQL'
            UPDATE user_memberships m
            INNER JOIN users u ON u.id = m.user_id
            SET m.permission = :permission, m.updated_at = UTC_TIMESTAMP(6)
            WHERE m.id = :id AND u.team_id = :team_id
            SQL,
            [':permission' => $permission, ':id' => $id, ':team_id' => $teamId],
        )->execute();

        return $affected > 0 ? $this->findUserById($teamId, $id) : null;
    }

    /** @return array<string,mixed>|null */
    public function updateGroup(string $teamId, string $id, string $permission): ?array
    {
        $affected = $this->database->createCommand(
            <<<'SQL'
            UPDATE group_memberships m
            INNER JOIN groups g ON g.id = m.group_id
            SET m.permission = :permission, m.updated_at = UTC_TIMESTAMP(6)
            WHERE m.id = :id AND g.team_id = :team_id
            SQL,
            [':permission' => $permission, ':id' => $id, ':team_id' => $teamId],
        )->execute();

        return $affected > 0 ? $this->findGroupById($teamId, $id) : null;
    }

    public function deleteUser(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            DELETE m FROM user_memberships m
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.id = :id AND u.team_id = :team_id
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }

    public function deleteGroup(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            DELETE m FROM group_memberships m
            INNER JOIN groups g ON g.id = m.group_id
            WHERE m.id = :id AND g.team_id = :team_id
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }

    /** @return array<string,mixed>|null */
    private function findUser(string $teamId, string $userId, ?string $collectionId, ?string $documentId): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT m.* FROM user_memberships m
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.user_id = :user_id
              AND ((m.collection_id = :collection_id) OR (m.collection_id IS NULL AND :collection_id IS NULL))
              AND ((m.document_id = :document_id) OR (m.document_id IS NULL AND :document_id IS NULL))
              AND u.team_id = :team_id
            LIMIT 1
            SQL,
            [
                ':user_id' => $userId,
                ':collection_id' => $collectionId,
                ':document_id' => $documentId,
                ':team_id' => $teamId,
            ],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function findGroup(string $teamId, string $groupId, ?string $collectionId, ?string $documentId): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT m.* FROM group_memberships m
            INNER JOIN groups g ON g.id = m.group_id
            WHERE m.group_id = :group_id
              AND ((m.collection_id = :collection_id) OR (m.collection_id IS NULL AND :collection_id IS NULL))
              AND ((m.document_id = :document_id) OR (m.document_id IS NULL AND :document_id IS NULL))
              AND g.team_id = :team_id
            LIMIT 1
            SQL,
            [
                ':group_id' => $groupId,
                ':collection_id' => $collectionId,
                ':document_id' => $documentId,
                ':team_id' => $teamId,
            ],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function findUserById(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT m.* FROM user_memberships m
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.id = :id AND u.team_id = :team_id LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function findGroupById(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT m.* FROM group_memberships m
            INNER JOIN groups g ON g.id = m.group_id
            WHERE m.id = :id AND g.team_id = :team_id LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();
        return is_array($row) ? $row : null;
    }

    private function assertTarget(string $teamId, ?string $collectionId, ?string $documentId): void
    {
        if (($collectionId === null) === ($documentId === null)) {
            throw new RuntimeException('Specify exactly one membership target.');
        }
        $table = $collectionId !== null ? 'collections' : 'documents';
        $id = $collectionId ?? $documentId;
        $exists = (int) $this->database->createCommand(
            "SELECT COUNT(*) FROM {$table} WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL",
            [':id' => $id, ':team_id' => $teamId],
        )->queryScalar();
        if ($exists !== 1) {
            throw new RuntimeException('Membership target not found.');
        }
    }

    private function assertEntity(string $table, string $teamId, string $id): void
    {
        $exists = (int) $this->database->createCommand(
            "SELECT COUNT(*) FROM {$table} WHERE id = :id AND team_id = :team_id",
            [':id' => $id, ':team_id' => $teamId],
        )->queryScalar();
        if ($exists !== 1) {
            throw new RuntimeException('Membership subject not found.');
        }
    }
}
