<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\CommentPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Comment\CommentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class CommentsResolveAction
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

        $body = RequestData::body($request);
        $id = RequestData::string($body, 'id');
        $row = $id === null ? null : $comments->resolve(
            (string) $auth['team_id'],
            $id,
            (string) $auth['user_id'],
            RequestData::bool($body, 'resolved', true),
        );
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Comment not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => CommentPresenter::comment($row),
            'policies' => [CommentPresenter::policy(
                (string) $row['id'],
                (string) ($row['user_id'] ?? '') === (string) $auth['user_id'],
                ($auth['role'] ?? null) === 'admin',
            )],
        ]);
    }
}
