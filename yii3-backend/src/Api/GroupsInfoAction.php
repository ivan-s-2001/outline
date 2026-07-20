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

final readonly class GroupsInfoAction
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

        $id = RequestData::string(RequestData::body($request), 'id');
        $row = $id === null ? null : $groups->find((string) $auth['team_id'], $id);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Group not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => GroupPresenter::group($row),
            'policies' => [GroupPresenter::policy($id, ($auth['role'] ?? null) === 'admin')],
        ]);
    }
}
