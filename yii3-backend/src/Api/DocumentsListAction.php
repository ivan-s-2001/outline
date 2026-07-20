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
use Yiisoft\Http\Status;

final readonly class DocumentsListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        DocumentService $documents,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $parent = array_key_exists('parentDocumentId', $body) ? $body['parentDocumentId'] : false;
        $statuses = isset($body['statusFilter']) && is_array($body['statusFilter'])
            ? array_values(array_map('strtolower', array_map('strval', $body['statusFilter'])))
            : [];
        $offset = max(0, RequestData::int($body, 'offset'));
        $limit = max(1, min(100, RequestData::int($body, 'limit', 25)));

        $rows = $documents->list(
            (string) $auth['team_id'],
            RequestData::nullableString($body, 'collectionId'),
            $parent,
            $statuses,
            $offset,
            $limit,
        );

        $data = [];
        $policies = [];
        foreach ($rows as $row) {
            $data[] = OutlinePresenter::document($row);
            $policies[] = PolicyPresenter::document((string) $row['id']);
        }

        return $responseFactory->createResponse([
            'data' => $data,
            'policies' => $policies,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
            ],
        ]);
    }
}
