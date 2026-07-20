<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class StarsListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        ConnectionInterface $database,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $rows = $database->createCommand(
            <<<'SQL'
            SELECT s.*,
                   d.title AS document_title, d.text AS document_text, d.url_id AS document_url_id,
                   d.collection_id AS document_collection_id, d.parent_document_id AS document_parent_id,
                   d.created_at AS document_created_at, d.updated_at AS document_updated_at,
                   c.name AS collection_name, c.description AS collection_description,
                   c.permission AS collection_permission, c.document_structure,
                   c.created_at AS collection_created_at, c.updated_at AS collection_updated_at
            FROM stars s
            LEFT JOIN documents d ON d.id = s.document_id AND d.deleted_at IS NULL
            LEFT JOIN collections c ON c.id = s.collection_id AND c.deleted_at IS NULL
            WHERE s.user_id = :user_id
            ORDER BY s.index_key ASC, s.created_at ASC
            SQL,
            [':user_id' => $auth['user_id']],
        )->queryAll();

        $stars = [];
        $documents = [];
        $collections = [];
        foreach ($rows as $row) {
            $stars[] = [
                'id' => (string) $row['id'],
                'userId' => (string) $row['user_id'],
                'documentId' => $row['document_id'] ?? null,
                'collectionId' => $row['collection_id'] ?? null,
                'index' => $row['index_key'] ?? null,
                'createdAt' => $row['created_at'] ?? null,
            ];
            if ($row['document_id'] !== null && $row['document_title'] !== null) {
                $documents[] = OutlinePresenter::document([
                    'id' => $row['document_id'],
                    'title' => $row['document_title'],
                    'text' => $row['document_text'],
                    'url_id' => $row['document_url_id'],
                    'collection_id' => $row['document_collection_id'],
                    'parent_document_id' => $row['document_parent_id'],
                    'created_at' => $row['document_created_at'],
                    'updated_at' => $row['document_updated_at'],
                ]);
            }
            if ($row['collection_id'] !== null && $row['collection_name'] !== null) {
                $collections[] = OutlinePresenter::collection([
                    'id' => $row['collection_id'],
                    'name' => $row['collection_name'],
                    'description' => $row['collection_description'],
                    'permission' => $row['collection_permission'],
                    'document_structure' => $row['document_structure'],
                    'created_at' => $row['collection_created_at'],
                    'updated_at' => $row['collection_updated_at'],
                ]);
            }
        }

        return $responseFactory->createResponse([
            'data' => [
                'stars' => $stars,
                'documents' => $documents,
                'collections' => $collections,
            ],
        ]);
    }
}
