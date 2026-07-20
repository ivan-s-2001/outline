<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\RequestData;
use App\Api\Shared\SharePresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Collection\CollectionService;
use App\Domain\Document\DocumentService;
use App\Domain\Share\ShareService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class SharesInfoAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        ShareService $shares,
        CollectionService $collections,
        DocumentService $documents,
        ConnectionInterface $database,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $body = RequestData::body($request);
        $publicId = RequestData::nullableString($body, 'id');
        $auth = $sessions->authenticate($request);

        if ($publicId === null) {
            if ($auth === null) {
                return $responseFactory->createResponse(['error' => 'authentication_required'])
                    ->withStatus(Status::UNAUTHORIZED);
            }
            $documentId = RequestData::nullableString($body, 'documentId');
            $collectionId = RequestData::nullableString($body, 'collectionId');
            $row = $database->createCommand(
                <<<'SQL'
                SELECT * FROM shares
                WHERE team_id = :team_id
                  AND revoked_at IS NULL
                  AND ((document_id = :document_id) OR (collection_id = :collection_id))
                ORDER BY created_at DESC
                LIMIT 1
                SQL,
                [
                    ':team_id' => $auth['team_id'],
                    ':document_id' => $documentId,
                    ':collection_id' => $collectionId,
                ],
            )->queryOne();

            if (!is_array($row)) {
                return $responseFactory->createResponse(null)->withStatus(Status::NO_CONTENT);
            }

            $write = ($auth['role'] ?? null) === 'admin'
                || (string) ($row['created_by_id'] ?? '') === (string) $auth['user_id'];
            return $responseFactory->createResponse([
                'data' => ['shares' => [SharePresenter::share($row)]],
                'policies' => [SharePresenter::policy((string) $row['id'], $write)],
            ]);
        }

        $share = $shares->findPublic($publicId);
        if ($share === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Share not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        $teamRow = $database->createCommand(
            'SELECT * FROM teams WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            [':id' => $share['team_id']],
        )->queryOne();
        if (!is_array($teamRow)) {
            return $responseFactory->createResponse(['error' => 'not_found'])->withStatus(Status::NOT_FOUND);
        }

        $collection = null;
        $document = null;
        $sharedTree = [];
        if ($share['collection_id'] !== null) {
            $collection = $collections->find((string) $share['team_id'], (string) $share['collection_id']);
            if ($collection !== null) {
                $sharedTree = json_decode((string) ($collection['document_structure'] ?? '[]'), true) ?: [];
            }
            $requestedDocumentId = RequestData::nullableString($body, 'documentId');
            if ($requestedDocumentId !== null) {
                $candidate = $documents->find((string) $share['team_id'], $requestedDocumentId);
                if ($candidate !== null && (string) ($candidate['collection_id'] ?? '') === (string) $share['collection_id']) {
                    $document = $candidate;
                }
            }
        } elseif ($share['document_id'] !== null) {
            $root = $documents->find((string) $share['team_id'], (string) $share['document_id']);
            $requestedDocumentId = RequestData::nullableString($body, 'documentId');
            $document = $root;
            if ($requestedDocumentId !== null && $requestedDocumentId !== (string) $share['document_id']) {
                $allowed = (bool) $share['include_child_documents'] && $this->isDescendant(
                    $database,
                    (string) $share['team_id'],
                    $requestedDocumentId,
                    (string) $share['document_id'],
                );
                $document = $allowed ? $documents->find((string) $share['team_id'], $requestedDocumentId) : null;
            }
            if ($root !== null) {
                $sharedTree = [['id' => $root['id'], 'children' => []]];
                if ($root['collection_id'] !== null) {
                    $collection = $collections->find((string) $share['team_id'], (string) $root['collection_id']);
                }
            }
        }

        if ($document === null && $collection === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Shared content not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        $shares->touch((string) $share['id']);
        return $responseFactory->createResponse([
            'data' => [
                'shares' => [SharePresenter::share($share)],
                'sharedTree' => $sharedTree,
                'team' => [
                    'id' => (string) $teamRow['id'],
                    'name' => (string) $teamRow['name'],
                    'avatarUrl' => $teamRow['avatar_url'] ?? null,
                    'color' => $teamRow['color'] ?? null,
                ],
                'collection' => $collection !== null ? OutlinePresenter::collection($collection) : null,
                'document' => $document !== null ? OutlinePresenter::document($document) : null,
            ],
            'policies' => [SharePresenter::policy((string) $share['id'], false)],
        ]);
    }

    private function isDescendant(
        ConnectionInterface $database,
        string $teamId,
        string $candidateId,
        string $rootId,
    ): bool {
        $found = $database->createCommand(
            <<<'SQL'
            WITH RECURSIVE ancestors AS (
                SELECT id, parent_document_id
                FROM documents
                WHERE id = :candidate_id AND team_id = :team_id AND deleted_at IS NULL
                UNION ALL
                SELECT d.id, d.parent_document_id
                FROM documents d
                INNER JOIN ancestors a ON a.parent_document_id = d.id
                WHERE d.team_id = :team_id AND d.deleted_at IS NULL
            )
            SELECT COUNT(*) FROM ancestors WHERE id = :root_id
            SQL,
            [':candidate_id' => $candidateId, ':team_id' => $teamId, ':root_id' => $rootId],
        )->queryScalar();

        return (int) $found > 0;
    }
}
