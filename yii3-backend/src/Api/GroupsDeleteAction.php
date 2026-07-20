<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Group\GroupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class GroupsDeleteAction
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

        $id = RequestData::string(RequestData::body($request), 'id');
        if ($id === null || !$groups->delete((string) $auth['team_id'], $id)) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Group not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse(['success' => true]);
    }
}
