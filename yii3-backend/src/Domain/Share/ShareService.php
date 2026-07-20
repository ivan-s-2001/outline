<?php

declare(strict_types=1);

namespace App\Domain\Share;

use Ramsey\Uuid\Uuid;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class ShareService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $teamId, ?string $query, int $offset, int $limit): array
    {
        $where = ['s.team_id = :team_id', 's.revoked_at IS NULL'];
        $params = [':team_id' => $teamId];
        if ($query !== null && $query !== '') {
            $where[] = '(d.title LIKE :query OR c.name LIKE :query)';
            $params[':query'] = '%' . addcslashes($query, '%_') . '%';
        }
        $offset = max(0, $offset);
        $limit = max(1, min(100, $limit));

        return $this->database->createCommand(
            'SELECT s.*, d.title AS document_title, c.name AS collection_name '
            . 'FROM shares s '
            . 'LEFT JOIN documents d ON d.id = s.document_id '
            . 'LEFT JOIN collections c ON c.id = s.collection_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY s.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        )->queryAll();
    }

    /** @return array<string, mixed>|null */
    public function find(string $teamId, string $id): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM shares
            WHERE (id = :id OR url_id = :id) AND team_id = :team_id
            LIMIT 1
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findPublic(string $urlId): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM shares
            WHERE url_id = :url_id
              AND published = 1
              AND revoked_at IS NULL
            LIMIT 1
            SQL,
            [':url_id' => $urlId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(
        string $teamId,
        string $userId,
        ?string $documentId,
        ?string $collectionId,
        bool $includeChildDocuments,
        bool $allowIndexing,
    ): array {
        if (($documentId === null) === ($collectionId === null)) {
            throw new \RuntimeException('Specify exactly one share target.');
        }

        $targetTable = $documentId !== null ? 'documents' : 'collections';
        $targetId = $documentId ?? $collectionId;
        $exists = (int) $this->database->createCommand(
            "SELECT COUNT(*) FROM {$targetTable} WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL",
            [':id' => $targetId, ':team_id' => $teamId],
        )->queryScalar();
        if ($exists !== 1) {
            throw new \RuntimeException('Share target not found.');
        }

        $existing = $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM shares
            WHERE team_id = :team_id
              AND ((document_id = :document_id) OR (collection_id = :collection_id))
              AND revoked_at IS NULL
            LIMIT 1
            SQL,
            [
                ':team_id' => $teamId,
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
            ],
        )->queryOne();
        if (is_array($existing)) {
            return $existing;
        }

        $id = Uuid::uuid4()->toString();
        $urlId = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO shares (
                id, team_id, document_id, collection_id, created_by_id, url_id,
                published, include_child_documents, allow_indexing, created_at, updated_at
            ) VALUES (
                :id, :team_id, :document_id, :collection_id, :created_by_id, :url_id,
                1, :include_child_documents, :allow_indexing, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
            )
            SQL,
            [
                ':id' => $id,
                ':team_id' => $teamId,
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
                ':created_by_id' => $userId,
                ':url_id' => $urlId,
                ':include_child_documents' => $includeChildDocuments ? 1 : 0,
                ':allow_indexing' => $allowIndexing ? 1 : 0,
            ],
        )->execute();

        return $this->find($teamId, $id) ?? throw new \RuntimeException('Created share was not found.');
    }

    /** @return array<string, mixed>|null */
    public function update(string $teamId, string $id, array $changes): ?array
    {
        if ($this->find($teamId, $id) === null) {
            return null;
        }
        $allowed = [
            'published' => 'published',
            'includeChildDocuments' => 'include_child_documents',
            'allowIndexing' => 'allow_indexing',
        ];
        $sets = [];
        $params = [':id' => $id, ':team_id' => $teamId];
        foreach ($allowed as $input => $column) {
            if (!array_key_exists($input, $changes)) {
                continue;
            }
            $placeholder = ':v_' . $column;
            $sets[] = "`{$column}` = {$placeholder}";
            $params[$placeholder] = filter_var($changes[$input], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if ($sets !== []) {
            $sets[] = 'updated_at = UTC_TIMESTAMP(6)';
            $this->database->createCommand(
                'UPDATE shares SET ' . implode(', ', $sets) . ' WHERE id = :id AND team_id = :team_id',
                $params,
            )->execute();
        }

        return $this->find($teamId, $id);
    }

    public function revoke(string $teamId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE shares
            SET published = 0, revoked_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id AND revoked_at IS NULL
            SQL,
            [':id' => $id, ':team_id' => $teamId],
        )->execute() > 0;
    }

    public function touch(string $id): void
    {
        $this->database->createCommand(
            'UPDATE shares SET last_accessed_at = UTC_TIMESTAMP(6) WHERE id = :id',
            [':id' => $id],
        )->execute();
    }
}
