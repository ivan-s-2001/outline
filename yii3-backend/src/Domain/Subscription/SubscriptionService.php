<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

use Ramsey\Uuid\Uuid;
use RuntimeException;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class SubscriptionService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $teamId, string $userId, ?string $documentId, ?string $collectionId): array
    {
        $where = ['s.user_id = :user_id'];
        $params = [':user_id' => $userId, ':team_id' => $teamId];
        if ($documentId !== null) {
            $where[] = 's.document_id = :document_id';
            $params[':document_id'] = $documentId;
        }
        if ($collectionId !== null) {
            $where[] = 's.collection_id = :collection_id';
            $params[':collection_id'] = $collectionId;
        }

        return $this->database->createCommand(
            'SELECT s.* FROM subscriptions s '
            . 'LEFT JOIN documents d ON d.id = s.document_id '
            . 'LEFT JOIN collections c ON c.id = s.collection_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' AND (s.document_id IS NULL OR d.team_id = :team_id) '
            . 'AND (s.collection_id IS NULL OR c.team_id = :team_id) '
            . 'ORDER BY s.created_at DESC',
            $params,
        )->queryAll();
    }

    /** @return array<string, mixed> */
    public function create(
        string $teamId,
        string $userId,
        ?string $documentId,
        ?string $collectionId,
        string $eventType,
    ): array {
        if (($documentId === null) === ($collectionId === null)) {
            throw new RuntimeException('Specify exactly one subscription target.');
        }
        $table = $documentId !== null ? 'documents' : 'collections';
        $targetId = $documentId ?? $collectionId;
        $exists = (int) $this->database->createCommand(
            "SELECT COUNT(*) FROM {$table} WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL",
            [':id' => $targetId, ':team_id' => $teamId],
        )->queryScalar();
        if ($exists !== 1) {
            throw new RuntimeException('Subscription target not found.');
        }

        $id = Uuid::uuid4()->toString();
        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO subscriptions (id, user_id, document_id, collection_id, event_type, created_at)
            VALUES (:id, :user_id, :document_id, :collection_id, :event_type, UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE id = id
            SQL,
            [
                ':id' => $id,
                ':user_id' => $userId,
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
                ':event_type' => $eventType,
            ],
        )->execute();

        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM subscriptions
            WHERE user_id = :user_id
              AND event_type = :event_type
              AND ((document_id = :document_id) OR (collection_id = :collection_id))
            LIMIT 1
            SQL,
            [
                ':user_id' => $userId,
                ':event_type' => $eventType,
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
            ],
        )->queryOne();
        if (!is_array($row)) {
            throw new RuntimeException('Created subscription was not found.');
        }

        return $row;
    }

    public function delete(string $userId, string $id): bool
    {
        return $this->database->createCommand(
            'DELETE FROM subscriptions WHERE id = :id AND user_id = :user_id',
            [':id' => $id, ':user_id' => $userId],
        )->execute() > 0;
    }
}
