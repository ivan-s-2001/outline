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

final readonly class CollectionsCreateAction
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
        $name = RequestData::string($body, 'name');
        if ($name === null || $name === '') {
            return $responseFactory->createResponse([
                'error' => 'validation_error',
                'message' => 'Collection name is required',
            ])->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $permission = RequestData::string($body, 'permission', 'read_write') ?? 'read_write';
        if (!in_array($permission, ['read', 'read_write', 'admin'], true)) {
            $permission = 'read_write';
        }

        $row = $collections->create(
            (string) $auth['team_id'],
            (string) $auth['user_id'],
            $name,
            RequestData::nullableString($body, 'description'),
            $permission,
            RequestData::nullableString($body, 'color'),
            RequestData::nullableString($body, 'icon'),
        );

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::collection($row),
            'policies' => [PolicyPresenter::collection((string) $row['id'], (string) $row['permission'])],
        ])->withStatus(Status::CREATED);
    }
}
