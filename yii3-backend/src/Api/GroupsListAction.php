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

final readonly class GroupsListAction
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

        $body = RequestData::body($request);
        $offset = max(0, RequestData::int($body, 'offset'));
        $limit = max(1, min(100, RequestData::int($body, 'limit', 25)));
        $rows = $groups->list(
            (string) $auth['team_id'],
            RequestData::nullableString($body, 'query'),
            RequestData::nullableString($body, 'userId'),
            $offset,
            $limit,
        );
        $admin = ($auth['role'] ?? null) === 'admin';
        $presented = array_map(GroupPresenter::group(...), $rows);
        $policies = array_map(
            static fn(array $row): array => GroupPresenter::policy((string) $row['id'], $admin),
            $rows,
        );

        return $responseFactory->createResponse([
            'data' => [
                'groups' => $presented,
                'groupMemberships' => [],
            ],
            'policies' => $policies,
            'pagination' => ['offset' => $offset, 'limit' => $limit, 'total' => count($rows)],
        ]);
    }
}
