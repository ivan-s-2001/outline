<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\MembershipPresenter;
use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Membership\MembershipService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class UserMembershipsListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        MembershipService $memberships,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        try {
            $rows = $memberships->listUsers(
                (string) $auth['team_id'],
                RequestData::nullableString($body, 'collectionId'),
                RequestData::nullableString($body, 'documentId'),
                RequestData::nullableString($body, 'userId'),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $admin = ($auth['role'] ?? null) === 'admin';
        $users = [];
        foreach ($rows as $row) {
            $users[(string) $row['user_id']] = OutlinePresenter::user([
                'id' => $row['user_id'],
                'team_id' => $auth['team_id'],
                'name' => $row['user_name'] ?? '',
                'email' => $row['user_email'] ?? '',
                'avatar_url' => $row['user_avatar_url'] ?? null,
                'role' => $row['user_role'] ?? 'member',
                'state' => $row['user_state'] ?? 'active',
            ]);
        }

        return $responseFactory->createResponse([
            'data' => [
                'users' => array_values($users),
                'memberships' => array_map(MembershipPresenter::user(...), $rows),
            ],
            'policies' => array_map(
                static fn(array $row): array => MembershipPresenter::policy((string) $row['id'], $admin),
                $rows,
            ),
        ]);
    }
}
