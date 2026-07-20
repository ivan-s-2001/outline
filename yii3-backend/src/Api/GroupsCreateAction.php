<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\GroupPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Group\GroupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class GroupsCreateAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        GroupService $groups,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }
        if (($auth['role'] ?? null) !== 'admin') {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $body = RequestData::body($request);
        $name = RequestData::string($body, 'name');
        if ($name === null || $name === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'name is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $row = $groups->create(
                (string) $auth['team_id'],
                $name,
                RequestData::nullableString($body, 'externalId'),
            );
        } catch (Throwable) {
            return $responseFactory->createResponse(['error' => 'conflict', 'message' => 'Group name already exists'])
                ->withStatus(Status::CONFLICT);
        }

        return $responseFactory->createResponse([
            'data' => GroupPresenter::group($row),
            'policies' => [GroupPresenter::policy((string) $row['id'], true)],
        ])->withStatus(Status::CREATED);
    }
}
