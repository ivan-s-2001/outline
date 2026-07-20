<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Team\TeamService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class TeamsInfoAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        TeamService $teams,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $row = $teams->find((string) $auth['team_id']);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Workspace not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::team($row),
            'policies' => [PolicyPresenter::team(
                (string) $row['id'],
                ($auth['role'] ?? null) === 'admin',
            )],
        ]);
    }
}
