<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Collection\CollectionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class CollectionsListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        CollectionService $collections,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $rows = $collections->list(
            (string) $auth['team_id'],
            RequestData::bool($body, 'includeArchived', false),
        );

        $data = [];
        $policies = [];
        foreach ($rows as $row) {
            $data[] = OutlinePresenter::collection($row);
            $policies[] = PolicyPresenter::collection((string) $row['id'], (string) $row['permission']);
        }

        return $responseFactory->createResponse([
            'data' => $data,
            'policies' => $policies,
            'pagination' => [
                'offset' => 0,
                'limit' => count($data),
            ],
        ]);
    }
}
