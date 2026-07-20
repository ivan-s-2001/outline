<?php

declare(strict_types=1);

namespace App\Domain\Document;

use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class DocumentService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return array<string, mixed>|null */
    public function find(string $teamId, string $idOrUrlId, bool $includeDeleted = false): ?array
    {
        $deleted = $includeDeleted ? '' : 'AND deleted_at IS NULL';
        $row = $this->database->createCommand(
            <<<SQL
            SELECT *
            FROM documents
            WHERE team_id = :team_id
              AND (id = :identifier OR url_id = :identifier)
              {$deleted}
            LIMIT 1
            SQL,
            [':team_id' => $teamId, ':identifier' => $idOrUrlId],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function list(
        string $teamId,
        ?string $collectionId,
        mixed $parentDocumentId,
        array $statuses,
        int $offset,
        int $limit,
    ): array {
        $where = ['team_id = :team_id', 'deleted_at IS NULL'];
        $params = [':team_id' => $teamId];

        if ($collectionId !== null && $collectionId !== '') {
            $where[] = 'collection_id = :collection_id';
            $params[':collection_id'] = $collectionId;
        }

        if ($parentDocumentId === null) {
            $where[] = 'parent_document_id IS NULL';
        } elseif (is_string($parentDocumentId) && $parentDocumentId !== '') {
            $where[] = 'parent_document_id = :parent_document_id';
            $params[':parent_document_id'] = $parentDocumentId;
        }

        if ($statuses !== []) {
            $statusParts = [];
            if (in_array('published', $statuses, true)) {
                $statusParts[] = '(published_at IS NOT NULL AND archived_at IS NULL)';
            }
            if (in_array('draft', $statuses, true)) {
                $statusParts[] = 'published_at IS NULL';
            }
            if (in_array('archived', $statuses, true)) {
                $statusParts[] = 'archived_at IS NOT NULL';
            }
            if ($statusParts !== []) {
                $where[] = '(' . implode(' OR ', $statusParts) . ')';
            }
        } else {
            $where[] = 'archived_at IS NULL';
        }

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        return $this->database->createCommand(
            'SELECT * FROM documents WHERE ' . implode(' AND ', $where)
            . ' ORDER BY updated_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        )->queryAll();
    }

    /** @return array<string, mixed> */
    public function create(
        string $teamId,
        string $userId,
        ?string $collectionId,
        ?string $parentDocumentId,
        string $title,
        string $text,
        mixed $content,
        bool $publish,
        ?string $templateId = null,
        ?string $icon = null,
        ?string $color = null,
    ): array {
        if ($collectionId !== null) {
            $this->assertCollection($teamId, $collectionId);
        }
        if ($parentDocumentId !== null) {
            $parent = $this->find($teamId, $parentDocumentId);
            if ($parent === null) {
                throw new RuntimeException('Parent document not found.');
            }
            if ($collectionId === null) {
                $collectionId = $parent['collection_id'] !== null ? (string) $parent['collection_id'] : null;
            }
        }

        $id = Uuid::uuid4()->toString();
        $revisionId = Uuid::uuid4()->toString();
        $urlId = substr(bin2hex(random_bytes(8)), 0, 12);
        $indexKey = 'z' . bin2hex(random_bytes(6));
        $publishedAt = $publish ? gmdate('Y-m-d H:i:s.u') : null;
        $contentJson = $content === null || is_string($content)
            ? $content
            : json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $transaction = $this->database->beginTransaction();
        try {
            $this->database->createCommand(
                <<<'SQL'
                INSERT INTO documents (
                    id, team_id, collection_id, parent_document_id, template_id,
                    title, text, content, url_id, index_key, icon, color,
                    created_by_id, updated_by_id, revision_number, published_at,
                    created_at, updated_at
                ) VALUES (
                    :id, :team_id, :collection_id, :parent_document_id, :template_id,
                    :title, :text, :content, :url_id, :index_key, :icon, :color,
                    :created_by_id, :updated_by_id, 1, :published_at,
                    UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
                )
                SQL,
                [
                    ':id' => $id,
                    ':team_id' => $teamId,
                    ':collection_id' => $collectionId,
                    ':parent_document_id' => $parentDocumentId,
                    ':template_id' => $templateId,
                    ':title' => $title,
                    ':text' => $text,
                    ':content' => $contentJson,
                    ':url_id' => $urlId,
                    ':index_key' => $indexKey,
                    ':icon' => $icon,
                    ':color' => $color,
                    ':created_by_id' => $userId,
                    ':updated_by_id' => $userId,
                    ':published_at' => $publishedAt,
                ],
            )->execute();

            $this->database->createCommand(
                <<<'SQL'
                INSERT INTO revisions (id, document_id, user_id, title, text, content, version, created_at)
                VALUES (:id, :document_id, :user_id, :title, :text, :content, 1, UTC_TIMESTAMP(6))
                SQL,
                [
                    ':id' => $revisionId,
                    ':document_id' => $id,
                    ':user_id' => $userId,
                    ':title' => $title,
                    ':text' => $text,
                    ':content' => $contentJson,
                ],
            )->execute();

            if ($collectionId !== null) {
                $this->insertIntoStructure($collectionId, $id, $parentDocumentId);
            }

            $this->event($teamId, $userId, 'documents.create', $id, $id, $collectionId, ['title' => $title]);
            $transaction->commit();
        } catch (Throwable $exception) {
            if ($transaction->isActive()) {
                $transaction->rollBack();
            }
            throw $exception;
        }

        return $this->find($teamId, $id) ?? throw new RuntimeException('Created document was not found.');
    }

    /**
     * @return array{document:array<string,mixed>|null, conflict:bool}
     */
    public function update(
        string $teamId,
        string $userId,
        string $id,
        array $changes,
        ?int $expectedRevision = null,
    ): array {
        $transaction = $this->database->beginTransaction();
        try {
            $current = $this->database->createCommand(
                <<<'SQL'
                SELECT * FROM documents
                WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
                LIMIT 1 FOR UPDATE
                SQL,
                [':id' => $id, ':team_id' => $teamId],
            )->queryOne();

            if (!is_array($current)) {
                $transaction->commit();
                return ['document' => null, 'conflict' => false];
            }

            $currentRevision = (int) $current['revision_number'];
            if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
                $transaction->commit();
                return ['document' => $current, 'conflict' => true];
            }

            $allowed = [
                'title' => 'title',
                'text' => 'text',
                'content' => 'content',
                'icon' => 'icon',
                'color' => 'color',
                'templateId' => 'template_id',
            ];
            $sets = [];
            $params = [
                ':id' => $id,
                ':team_id' => $teamId,
                ':updated_by_id' => $userId,
            ];
            $contentChanged = false;

            foreach ($allowed as $input => $column) {
                if (!array_key_exists($input, $changes)) {
                    continue;
                }
                $placeholder = ':v_' . $column;
                $sets[] = "`{$column}` = {$placeholder}";
                $value = $changes[$input];
                if ($input === 'content' && $value !== null && !is_string($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                }
                $params[$placeholder] = $value;
                if (in_array($input, ['title', 'text', 'content'], true)) {
                    $contentChanged = true;
                }
            }

            if (array_key_exists('publish', $changes)) {
                $sets[] = 'published_at = :published_at';
                $params[':published_at'] = $changes['publish']
                    ? ($current['published_at'] ?: gmdate('Y-m-d H:i:s.u'))
                    : null;
            }
            if (array_key_exists('archived', $changes)) {
                $sets[] = 'archived_at = :archived_at';
                $params[':archived_at'] = $changes['archived'] ? gmdate('Y-m-d H:i:s.u') : null;
            }

            if ($sets === []) {
                $transaction->commit();
                return ['document' => $current, 'conflict' => false];
            }

            $newRevision = $contentChanged ? $currentRevision + 1 : $currentRevision;
            $sets[] = 'revision_number = :revision_number';
            $sets[] = 'updated_by_id = :updated_by_id';
            $sets[] = 'updated_at = UTC_TIMESTAMP(6)';
            $params[':revision_number'] = $newRevision;

            $this->database->createCommand(
                'UPDATE documents SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL',
                $params,
            )->execute();

            if ($contentChanged) {
                $updated = $this->database->createCommand(
                    'SELECT * FROM documents WHERE id = :id LIMIT 1',
                    [':id' => $id],
                )->queryOne();
                if (!is_array($updated)) {
                    throw new RuntimeException('Updated document was not found.');
                }
                $this->database->createCommand(
                    <<<'SQL'
                    INSERT INTO revisions (id, document_id, user_id, title, text, content, version, created_at)
                    VALUES (:id, :document_id, :user_id, :title, :text, :content, :version, UTC_TIMESTAMP(6))
                    SQL,
                    [
                        ':id' => Uuid::uuid4()->toString(),
                        ':document_id' => $id,
                        ':user_id' => $userId,
                        ':title' => $updated['title'],
                        ':text' => $updated['text'],
                        ':content' => $updated['content'],
                        ':version' => $newRevision,
                    ],
                )->execute();
            }

            $this->event(
                $teamId,
                $userId,
                'documents.update',
                $id,
                $id,
                $current['collection_id'] !== null ? (string) $current['collection_id'] : null,
                ['title' => $changes['title'] ?? $current['title'], 'revision' => $newRevision],
            );
            $transaction->commit();
        } catch (Throwable $exception) {
            if ($transaction->isActive()) {
                $transaction->rollBack();
            }
            throw $exception;
        }

        return ['document' => $this->find($teamId, $id), 'conflict' => false];
    }

    /** @return array<string, mixed>|null */
    public function move(
        string $teamId,
        string $userId,
        string $id,
        ?string $collectionId,
        ?string $parentDocumentId,
    ): ?array {
        $current = $this->find($teamId, $id);
        if ($current === null) {
            return null;
        }
        if ($collectionId !== null) {
            $this->assertCollection($teamId, $collectionId);
        }
        if ($parentDocumentId !== null) {
            if ($parentDocumentId === $id) {
                throw new RuntimeException('A document cannot be its own parent.');
            }
            $parent = $this->find($teamId, $parentDocumentId);
            if ($parent === null) {
                throw new RuntimeException('Parent document not found.');
            }
            $collectionId = $parent['collection_id'] !== null ? (string) $parent['collection_id'] : $collectionId;
        }

        $oldCollectionId = $current['collection_id'] !== null ? (string) $current['collection_id'] : null;
        $transaction = $this->database->beginTransaction();
        try {
            if ($oldCollectionId !== null) {
                $this->removeFromStructure($oldCollectionId, $id);
            }

            $this->database->createCommand(
                <<<'SQL'
                UPDATE documents
                SET collection_id = :collection_id,
                    parent_document_id = :parent_document_id,
                    updated_by_id = :updated_by_id,
                    updated_at = UTC_TIMESTAMP(6)
                WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
                SQL,
                [
                    ':collection_id' => $collectionId,
                    ':parent_document_id' => $parentDocumentId,
                    ':updated_by_id' => $userId,
                    ':id' => $id,
                    ':team_id' => $teamId,
                ],
            )->execute();

            if ($collectionId !== null) {
                $this->insertIntoStructure($collectionId, $id, $parentDocumentId);
            }

            $this->event($teamId, $userId, 'documents.move', $id, $id, $collectionId, [
                'collectionId' => $collectionId,
                'parentDocumentId' => $parentDocumentId,
            ]);
            $transaction->commit();
        } catch (Throwable $exception) {
            if ($transaction->isActive()) {
                $transaction->rollBack();
            }
            throw $exception;
        }

        return $this->find($teamId, $id);
    }

    public function delete(string $teamId, string $userId, string $id): bool
    {
        $document = $this->find($teamId, $id);
        if ($document === null) {
            return false;
        }

        $affected = $this->database->createCommand(
            <<<'SQL'
            UPDATE documents
            SET deleted_at = UTC_TIMESTAMP(6), updated_by_id = :user_id, updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL
            SQL,
            [':user_id' => $userId, ':id' => $id, ':team_id' => $teamId],
        )->execute();

        if ($affected > 0 && $document['collection_id'] !== null) {
            $this->removeFromStructure((string) $document['collection_id'], $id);
        }

        return $affected > 0;
    }

    /** @return array<string, mixed>|null */
    public function restore(string $teamId, string $userId, string $id): ?array
    {
        $document = $this->find($teamId, $id, true);
        if ($document === null || $document['deleted_at'] === null) {
            return $document;
        }

        $this->database->createCommand(
            <<<'SQL'
            UPDATE documents
            SET deleted_at = NULL, updated_by_id = :user_id, updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND team_id = :team_id
            SQL,
            [':user_id' => $userId, ':id' => $id, ':team_id' => $teamId],
        )->execute();

        if ($document['collection_id'] !== null) {
            $this->insertIntoStructure(
                (string) $document['collection_id'],
                $id,
                $document['parent_document_id'] !== null ? (string) $document['parent_document_id'] : null,
            );
        }

        return $this->find($teamId, $id);
    }

    /** @return list<array<string, mixed>> */
    public function search(string $teamId, string $query, ?string $collectionId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $collectionCondition = '';
        $params = [':team_id' => $teamId, ':query' => $query];
        if ($collectionId !== null && $collectionId !== '') {
            $collectionCondition = 'AND collection_id = :collection_id';
            $params[':collection_id'] = $collectionId;
        }

        $rows = $this->database->createCommand(
            <<<SQL
            SELECT *, MATCH(title, text) AGAINST(:query IN NATURAL LANGUAGE MODE) AS relevance
            FROM documents
            WHERE team_id = :team_id
              AND deleted_at IS NULL
              AND archived_at IS NULL
              {$collectionCondition}
              AND MATCH(title, text) AGAINST(:query IN NATURAL LANGUAGE MODE)
            ORDER BY relevance DESC, updated_at DESC
            LIMIT {$limit}
            SQL,
            $params,
        )->queryAll();

        if ($rows !== [] || mb_strlen($query) < 2) {
            return $rows;
        }

        $params[':like'] = '%' . addcslashes($query, '%_') . '%';
        return $this->database->createCommand(
            <<<SQL
            SELECT *, 0 AS relevance
            FROM documents
            WHERE team_id = :team_id
              AND deleted_at IS NULL
              AND archived_at IS NULL
              {$collectionCondition}
              AND (title LIKE :like OR text LIKE :like)
            ORDER BY updated_at DESC
            LIMIT {$limit}
            SQL,
            $params,
        )->queryAll();
    }

    /** @return list<array<string, mixed>> */
    public function revisions(string $teamId, string $documentId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));
        return $this->database->createCommand(
            <<<SQL
            SELECT r.*
            FROM revisions r
            INNER JOIN documents d ON d.id = r.document_id
            WHERE r.document_id = :document_id AND d.team_id = :team_id
            ORDER BY r.version DESC
            LIMIT {$limit}
            SQL,
            [':document_id' => $documentId, ':team_id' => $teamId],
        )->queryAll();
    }

    private function assertCollection(string $teamId, string $collectionId): void
    {
        $exists = (int) $this->database->createCommand(
            'SELECT COUNT(*) FROM collections WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL',
            [':id' => $collectionId, ':team_id' => $teamId],
        )->queryScalar();
        if ($exists !== 1) {
            throw new RuntimeException('Collection not found.');
        }
    }

    private function insertIntoStructure(string $collectionId, string $documentId, ?string $parentDocumentId): void
    {
        $row = $this->database->createCommand(
            'SELECT document_structure FROM collections WHERE id = :id LIMIT 1 FOR UPDATE',
            [':id' => $collectionId],
        )->queryOne();
        if (!is_array($row)) {
            throw new RuntimeException('Collection not found while updating document structure.');
        }

        $structure = json_decode((string) ($row['document_structure'] ?? '[]'), true);
        $structure = is_array($structure) ? $structure : [];
        $this->removeNode($structure, $documentId);
        $node = ['id' => $documentId, 'children' => []];

        if ($parentDocumentId === null || !$this->appendChild($structure, $parentDocumentId, $node)) {
            $structure[] = $node;
        }

        $this->database->createCommand(
            'UPDATE collections SET document_structure = :structure, updated_at = UTC_TIMESTAMP(6) WHERE id = :id',
            [
                ':structure' => json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ':id' => $collectionId,
            ],
        )->execute();
    }

    private function removeFromStructure(string $collectionId, string $documentId): void
    {
        $row = $this->database->createCommand(
            'SELECT document_structure FROM collections WHERE id = :id LIMIT 1 FOR UPDATE',
            [':id' => $collectionId],
        )->queryOne();
        if (!is_array($row)) {
            return;
        }

        $structure = json_decode((string) ($row['document_structure'] ?? '[]'), true);
        $structure = is_array($structure) ? $structure : [];
        if (!$this->removeNode($structure, $documentId)) {
            return;
        }

        $this->database->createCommand(
            'UPDATE collections SET document_structure = :structure, updated_at = UTC_TIMESTAMP(6) WHERE id = :id',
            [
                ':structure' => json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ':id' => $collectionId,
            ],
        )->execute();
    }

    private function appendChild(array &$nodes, string $parentId, array $child): bool
    {
        foreach ($nodes as &$node) {
            if (($node['id'] ?? null) === $parentId) {
                $node['children'] = is_array($node['children'] ?? null) ? $node['children'] : [];
                $node['children'][] = $child;
                return true;
            }
            if (isset($node['children']) && is_array($node['children']) && $this->appendChild($node['children'], $parentId, $child)) {
                return true;
            }
        }
        unset($node);
        return false;
    }

    private function removeNode(array &$nodes, string $documentId): bool
    {
        foreach ($nodes as $index => &$node) {
            if (($node['id'] ?? null) === $documentId) {
                array_splice($nodes, $index, 1);
                return true;
            }
            if (isset($node['children']) && is_array($node['children']) && $this->removeNode($node['children'], $documentId)) {
                return true;
            }
        }
        unset($node);
        return false;
    }

    private function event(
        string $teamId,
        string $userId,
        string $name,
        string $modelId,
        ?string $documentId,
        ?string $collectionId,
        array $data,
    ): void {
        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO events (team_id, user_id, name, model_id, document_id, collection_id, data, created_at)
            VALUES (:team_id, :user_id, :name, :model_id, :document_id, :collection_id, :data, UTC_TIMESTAMP(6))
            SQL,
            [
                ':team_id' => $teamId,
                ':user_id' => $userId,
                ':name' => $name,
                ':model_id' => $modelId,
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
                ':data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
        )->execute();
    }
}
