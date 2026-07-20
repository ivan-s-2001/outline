<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Comment\CommentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class CommentsDeleteAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        CommentService $comments,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $id = RequestData::string(RequestData::body($request), 'id');
        $current = $id === null ? null : $comments->find((string) $auth['team_id'], $id);
        if ($current === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Comment not found'])
                ->withStatus(Status::NOT_FOUND);
        }
        if ((string) ($current['user_id'] ?? '') !== (string) $auth['user_id'] && ($auth['role'] ?? null) !== 'admin') {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $comments->delete((string) $auth['team_id'], $id);
        return $responseFactory->createResponse(['success' => true]);
    }
}
