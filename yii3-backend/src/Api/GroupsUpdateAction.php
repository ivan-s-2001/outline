<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\GroupPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Group\GroupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class GroupsUpdateAction
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
        $id = RequestData::string($body, 'id');
        $name = RequestData::string($body, 'name');
        if ($id === null || $name === null || $name === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'id and name are required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $row = $groups->update((string) $auth['team_id'], $id, $name);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Group not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => GroupPresenter::group($row),
            'policies' => [GroupPresenter::policy($id, true)],
        ]);
    }
}
