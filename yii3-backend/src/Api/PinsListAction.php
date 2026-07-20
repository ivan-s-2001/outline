<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class PinsListAction
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

        $collectionId = RequestData::nullableString(RequestData::body($request), 'collectionId');
        $condition = $collectionId === null ? 'p.collection_id IS NULL' : 'p.collection_id = :collection_id';
        $params = [':team_id' => $auth['team_id']];
        if ($collectionId !== null) {
            $params[':collection_id'] = $collectionId;
        }

        $sql = <<<SQL
            SELECT d.*,
                   p.id AS pin_id,
                   p.collection_id AS pin_collection_id,
                   p.created_at AS pin_created_at,
                   p.index_key AS pin_index
            FROM pins p
            INNER JOIN documents d ON d.id = p.document_id
            WHERE p.team_id = :team_id
              AND {$condition}
              AND d.deleted_at IS NULL
            ORDER BY p.index_key ASC, p.created_at ASC
            SQL;
        $result = $database->createCommand($sql, $params)->queryAll();

        $pins = [];
        $documents = [];
        foreach ($result as $row) {
            $pins[] = [
                'id' => (string) $row['pin_id'],
                'documentId' => (string) $row['id'],
                'collectionId' => $row['pin_collection_id'] ?? null,
                'index' => $row['pin_index'] ?? null,
                'createdAt' => $row['pin_created_at'] ?? null,
            ];
            $documents[] = OutlinePresenter::document($row);
        }

        return $responseFactory->createResponse(['data' => [
            'pins' => $pins,
            'documents' => $documents,
        ]]);
    }
}
