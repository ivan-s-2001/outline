<?php

declare(strict_types=1);

namespace App\Domain\Comment;

use Ramsey\Uuid\Uuid;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class CommentService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $teamId, string $documentId): array
    {
        return $this->database->createCommand(
            <<<'SQL'
            SELECT c.*, u.name AS user_name, u.email AS user_email, u.avatar_url AS user_avatar_url
            FROM comments c
            INNER JOIN documents d ON d.id = c.document_id
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.document_id = :document_id
              AND d.team_id = :team_id
              AND d.deleted_at IS NULL
              AND c.deleted_at IS NULL
            ORDER BY c.created_at ASC
            SQL,
            [':document_id' => $documentId, ':team_id' => $teamId],
        )->queryAll();
    }

    /** @return array<string, mixed>|null */
    public function find(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT c.*, u.name AS user_name, u.email AS user_email, u.avatar_url AS user_avatar_url
            FROM comments c
            INNER JOIN documents d ON d.id = c.document_id
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.id = :id AND d.team_id = :team_id AND c.deleted_at IS NULL
            LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(string $teamId, string $documentId, string $userId, mixed $data, ?string $parentCommentId): array
    {
        $documentExists = (int) $this->database->createCommand(
            'SELECT COUNT(*) FROM documents WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL',
            [':id' => $documentId, ':team_id' => $teamId],
        )->queryScalar();
        if ($documentExists !== 1) {
            throw new \RuntimeException('Document not found.');
        }

        if ($parentCommentId !== null) {
            $parentExists = (int) $this->database->createCommand(
                'SELECT COUNT(*) FROM comments WHERE id = :id AND document_id = :document_id AND deleted_at IS NULL',
                [':id' => $parentCommentId, ':document_id' => $documentId],
            )->queryScalar();
            if ($parentExists !== 1) {
                throw new \RuntimeException('Parent comment not found.');
            }
        }

        $id = Uuid::uuid4()->toString();
        $dataJson = is_string($data)
            ? json_encode(['text' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO comments (id, document_id, user_id, parent_comment_id, data, created_at, updated_at)
            VALUES (:id, :document_id, :user_id, :parent_comment_id, :data, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
            SQL,
            [
                ':id' => $id,
                ':document_id' => $documentId,
                ':user_id' => $userId,
                ':parent_comment_id' => $parentCommentId,
                ':data' => $dataJson,
            ],
        )->execute();

        return $this->find($teamId, $id) ?? throw new \RuntimeException('Created comment was not found.');
    }

    /** @return array<string, mixed>|null */
    public function update(string $teamId, string $id, mixed $data): ?array
    {
        $dataJson = is_string($data)
            ? json_encode(['text' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $affected = $this->database->createCommand(
            <<<'SQL'
            UPDATE comments c
            INNER JOIN documents d ON d.id = c.document_id
            SET c.data = :data, c.updated_at = UTC_TIMESTAMP(6)
            WHERE c.id = :id AND d.team_id = :team_id AND c.deleted_at IS NULL
            SQL,
            [':data' => $dataJson, ':id' => $id, ':team_id' => $teamId],
        )->execute();

        return $affected > 0 ? $this->find($teamId, $id) : null;
    }

    /** @return array<string, mixed>|null */
    public function resolve(string $teamId, string $id, string $userId, bool $resolved): ?array
    {
        $affected = $this->database->createCommand(
            <<<'SQL'
            UPDATE comments c
            INNER JOIN documents d ON d.id = c.document_id
            SET c.resolved_at = :resolved_at,
                c.resolved_by_id = :resolved_by_id,
                c.updated_at = UTC_TIMESTAMP(6)
            WHERE c.id = :id AND d.team_id = :team_id AND c.deleted_at IS NULL
            SQL,
            [
                ':resolved_at' => $resolved ? gmdate('Y-m-d H:i:s.u') : null,
                ':resolved_by_id' => $resolved ? $userId : null,
                ':id' => $id,
                ':team_id' => $teamId,
            ],
        )->execute();

        return $affected > 0 ? $this->find($teamId, $id) : null;
    }

    public function delete(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE comments c
            INNER JOIN documents d ON d.id = c.document_id
            SET c.deleted_at = UTC_TIMESTAMP(6), c.updated_at = UTC_TIMESTAMP(6)
            WHERE c.id = :id AND d.team_id = :team_id AND c.deleted_at IS NULL
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }

    /** @return array<string, mixed>|null */
    public function addReaction(string $teamId, string $commentId, string $userId, string $emoji): ?array
    {
        if ($this->find($teamId, $commentId) === null) {
            return null;
        }

        $id = Uuid::uuid4()->toString();
        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO reactions (id, comment_id, user_id, emoji, created_at)
            VALUES (:id, :comment_id, :user_id, :emoji, UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE id = id
            SQL,
            [':id' => $id, ':comment_id' => $commentId, ':user_id' => $userId, ':emoji' => $emoji],
        )->execute();

        $row = $this->database->createCommand(
            'SELECT * FROM reactions WHERE comment_id = :comment_id AND user_id = :user_id AND emoji = :emoji LIMIT 1',
            [':comment_id' => $commentId, ':user_id' => $userId, ':emoji' => $emoji],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    public function removeReaction(string $teamId, string $commentId, string $userId, string $emoji): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            DELETE r FROM reactions r
            INNER JOIN comments c ON c.id = r.comment_id
            INNER JOIN documents d ON d.id = c.document_id
            WHERE r.comment_id = :comment_id
              AND r.user_id = :user_id
              AND r.emoji = :emoji
              AND d.team_id = :team_id
            SQL,
            [
                ':comment_id' => $commentId,
                ':user_id' => $userId,
                ':emoji' => $emoji,
                ':team_id' => $teamId,
            ],
        )->execute() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function reactions(string $teamId, array $commentIds): array
    {
        if ($commentIds === []) {
            return [];
        }
        $placeholders = [];
        $params = [':team_id' => $teamId];
        foreach (array_values($commentIds) as $index => $id) {
            $placeholder = ':comment_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        return $this->database->createCommand(
            'SELECT r.* FROM reactions r '
            . 'INNER JOIN comments c ON c.id = r.comment_id '
            . 'INNER JOIN documents d ON d.id = c.document_id '
            . 'WHERE d.team_id = :team_id AND r.comment_id IN (' . implode(',', $placeholders) . ') '
            . 'ORDER BY r.created_at',
            $params,
        )->queryAll();
    }
}
