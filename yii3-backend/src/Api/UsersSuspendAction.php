<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\User\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class UsersSuspendAction
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
        $id = RequestData::string($body, 'id');
        $suspended = RequestData::bool($body, 'suspended', true);
        if ($id === null || $id === (string) $auth['user_id']) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Cannot suspend the current user'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }
        if (!$users->suspend((string) $auth['team_id'], $id, $suspended)) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'User not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse(['success' => true]);
    }
}
