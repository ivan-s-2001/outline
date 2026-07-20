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

final readonly class UsersInfoAction
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
        $row = $id === null ? null : $users->find((string) $auth['team_id'], $id);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'User not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        $isAdmin = ($auth['role'] ?? null) === 'admin';
        return $responseFactory->createResponse([
            'data' => OutlinePresenter::user($row),
            'policies' => [PolicyPresenter::user($id, $id === (string) $auth['user_id'], $isAdmin)],
        ]);
    }
}
