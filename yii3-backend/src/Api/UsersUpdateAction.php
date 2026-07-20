<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\User\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class UsersUpdateAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        UserService $users,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $id = RequestData::string($body, 'id', (string) $auth['user_id']);
        $isAdmin = ($auth['role'] ?? null) === 'admin';
        if ($id === null || ($id !== (string) $auth['user_id'] && !$isAdmin)) {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $changes = $body;
        unset($changes['id']);
        if (isset($changes['role']) && !in_array($changes['role'], ['admin', 'member', 'viewer'], true)) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Invalid role'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $row = $users->update((string) $auth['team_id'], $id, $changes, $isAdmin);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'User not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::user($row),
            'policies' => [PolicyPresenter::user($id, $id === (string) $auth['user_id'], $isAdmin)],
        ]);
    }
}
