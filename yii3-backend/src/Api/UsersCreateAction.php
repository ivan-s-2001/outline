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
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class UsersCreateAction
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
        if (($auth['role'] ?? null) !== 'admin') {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $body = RequestData::body($request);
        $name = RequestData::string($body, 'name');
        $email = mb_strtolower(RequestData::string($body, 'email', '') ?? '');
        $role = RequestData::string($body, 'role', 'member') ?? 'member';
        if ($name === null || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Valid name and email are required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }
        if (!in_array($role, ['admin', 'member', 'viewer'], true)) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Invalid role'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $row = $users->create((string) $auth['team_id'], $name, $email, $role);
        } catch (Throwable $exception) {
            return $responseFactory->createResponse([
                'error' => 'conflict',
                'message' => 'A user with this email already exists',
            ])->withStatus(Status::CONFLICT);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::user($row),
            'policies' => [PolicyPresenter::user((string) $row['id'], false, true)],
        ])->withStatus(Status::CREATED);
    }
}
