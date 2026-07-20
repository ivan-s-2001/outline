<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Document\DocumentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class DocumentsSearchAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        DocumentService $documents,
        ConnectionInterface $database,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $query = RequestData::string($body, 'query', '') ?? '';
        $limit = max(1, min(100, RequestData::int($body, 'limit', 20)));
        if ($query === '') {
            return $responseFactory->createResponse(['data' => [], 'policies' => []]);
        }

        $rows = $documents->search(
            (string) $auth['team_id'],
            $query,
            RequestData::nullableString($body, 'collectionId'),
            $limit,
        );

        $database->createCommand(
            <<<'SQL'
            INSERT INTO search_queries (team_id, user_id, query, source, results, created_at)
            VALUES (:team_id, :user_id, :query, :source, :results, UTC_TIMESTAMP(6))
            SQL,
            [
                ':team_id' => $auth['team_id'],
                ':user_id' => $auth['user_id'],
                ':query' => $query,
                ':source' => RequestData::string($body, 'source', 'app'),
                ':results' => count($rows),
            ],
        )->execute();

        $data = [];
        $policies = [];
        foreach ($rows as $row) {
            $presented = OutlinePresenter::document($row);
            $presented['relevance'] = isset($row['relevance']) ? (float) $row['relevance'] : 0.0;
            $data[] = $presented;
            $policies[] = PolicyPresenter::document((string) $row['id']);
        }

        return $responseFactory->createResponse([
            'data' => $data,
            'policies' => $policies,
            'pagination' => ['offset' => 0, 'limit' => $limit],
        ]);
    }
}
