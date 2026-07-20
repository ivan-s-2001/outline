<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Group\GroupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class GroupsUsersListAction
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

        $groupId = RequestData::string(RequestData::body($request), 'id')
            ?? RequestData::string(RequestData::body($request), 'groupId');
        if ($groupId === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'groupId is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $rows = $groups->users((string) $auth['team_id'], $groupId);
        $users = [];
        $memberships = [];
        foreach ($rows as $row) {
            $users[] = OutlinePresenter::user($row);
            $memberships[] = [
                'groupId' => $groupId,
                'userId' => (string) $row['id'],
                'createdAt' => $row['membership_created_at'] ?? null,
            ];
        }

        return $responseFactory->createResponse([
            'data' => [
                'users' => $users,
                'groupMemberships' => $memberships,
            ],
        ]);
    }
}
