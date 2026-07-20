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

final readonly class UsersListAction
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
        $offset = max(0, RequestData::int($body, 'offset'));
        $limit = max(1, min(100, RequestData::int($body, 'limit', 25)));
        $rows = $users->list(
            (string) $auth['team_id'],
            RequestData::nullableString($body, 'query'),
            $offset,
            $limit,
            RequestData::bool($body, 'includeSuspended', false),
        );

        $isAdmin = ($auth['role'] ?? null) === 'admin';
        $data = [];
        $policies = [];
        foreach ($rows as $row) {
            $data[] = OutlinePresenter::user($row);
            $policies[] = PolicyPresenter::user(
                (string) $row['id'],
                (string) $row['id'] === (string) $auth['user_id'],
                $isAdmin,
            );
        }

        return $responseFactory->createResponse([
            'data' => $data,
            'policies' => $policies,
            'pagination' => ['offset' => $offset, 'limit' => $limit],
        ]);
    }
}
