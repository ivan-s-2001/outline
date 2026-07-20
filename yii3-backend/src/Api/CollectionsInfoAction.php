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

final readonly class CollectionsInfoAction
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

        $id = RequestData::string(RequestData::body($request), 'id');
        $row = $id === null ? null : $collections->find((string) $auth['team_id'], $id);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Collection not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::collection($row),
            'policies' => [PolicyPresenter::collection((string) $row['id'], (string) $row['permission'])],
        ]);
    }
}
