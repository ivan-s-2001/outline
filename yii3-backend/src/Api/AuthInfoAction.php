<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Auth\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class AuthInfoAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        TokenService $tokens,
        ConnectionInterface $database,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse([
                'error' => 'authentication_required',
                'message' => 'Authentication required',
            ])->withStatus(Status::UNAUTHORIZED);
        }

        $groups = $database->createCommand(
            <<<'SQL'
            SELECT g.id, g.name, g.external_id, gu.created_at
            FROM groups g
            INNER JOIN group_users gu ON gu.group_id = g.id
            WHERE gu.user_id = :user_id
            ORDER BY g.name
            SQL,
            [':user_id' => $auth['user_id']],
        )->queryAll();

        $presentedGroups = [];
        $groupUsers = [];
        foreach ($groups as $group) {
            $presentedGroups[] = [
                'id' => (string) $group['id'],
                'name' => (string) $group['name'],
                'externalId' => $group['external_id'] ?? null,
            ];
            $groupUsers[] = [
                'groupId' => (string) $group['id'],
                'userId' => (string) $auth['user_id'],
                'createdAt' => $group['created_at'] ?? null,
            ];
        }

        $isAdmin = ($auth['role'] ?? null) === 'admin';
        $team = OutlinePresenter::team($auth);
        $user = OutlinePresenter::user($auth);

        return $responseFactory->createResponse([
            'data' => [
                'user' => $user,
                'team' => $team,
                'groups' => $presentedGroups,
                'groupUsers' => $groupUsers,
                'collaborationToken' => $tokens->collaborationToken(
                    (string) $auth['user_id'],
                    (string) $auth['team_id'],
                ),
                'availableTeams' => [[
                    'id' => $team['id'],
                    'name' => $team['name'],
                    'avatarUrl' => $team['avatarUrl'],
                    'isSignedIn' => true,
                ]],
            ],
            'policies' => [
                PolicyPresenter::team((string) $auth['team_id'], $isAdmin),
                PolicyPresenter::user((string) $auth['user_id'], true, $isAdmin),
            ],
        ]);
    }
}
