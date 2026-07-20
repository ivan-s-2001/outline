<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Team\TeamService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class TeamsUpdateAction
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
        if (($auth['role'] ?? null) !== 'admin') {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $body = RequestData::body($request);
        if (isset($body['defaultUserRole']) && !in_array($body['defaultUserRole'], ['admin', 'member', 'viewer'], true)) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Invalid defaultUserRole'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $row = $teams->update((string) $auth['team_id'], $body);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Workspace not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::team($row),
            'policies' => [PolicyPresenter::team((string) $row['id'], true)],
        ]);
    }
}
