<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Share\ShareService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class SharesRevokeAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        ShareService $shares,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $id = RequestData::string(RequestData::body($request), 'id');
        $current = $id === null ? null : $shares->find((string) $auth['team_id'], $id);
        if ($current === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Share not found'])
                ->withStatus(Status::NOT_FOUND);
        }
        if (($auth['role'] ?? null) !== 'admin' && (string) ($current['created_by_id'] ?? '') !== (string) $auth['user_id']) {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $shares->revoke((string) $auth['team_id'], $id);
        return $responseFactory->createResponse(['success' => true]);
    }
}
