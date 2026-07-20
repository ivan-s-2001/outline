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

final readonly class TeamsListAction
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

        $rows = $teams->listForUser((string) $auth['user_id']);
        $admin = ($auth['role'] ?? null) === 'admin';
        return $responseFactory->createResponse([
            'data' => array_map(OutlinePresenter::team(...), $rows),
            'policies' => array_map(
                static fn(array $row): array => PolicyPresenter::team((string) $row['id'], $admin),
                $rows,
            ),
        ]);
    }
}
