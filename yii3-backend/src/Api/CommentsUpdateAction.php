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

final readonly class CommentsUpdateAction
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
        $data = $body['data'] ?? $body['text'] ?? null;
        $current = $id === null ? null : $comments->find((string) $auth['team_id'], $id);
        if ($current === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Comment not found'])
                ->withStatus(Status::NOT_FOUND);
        }
        $owner = (string) ($current['user_id'] ?? '') === (string) $auth['user_id'];
        $admin = ($auth['role'] ?? null) === 'admin';
        if (!$owner && !$admin) {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }
        if ($data === null || $data === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'data is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $row = $comments->update((string) $auth['team_id'], $id, $data);
        return $responseFactory->createResponse([
            'data' => CommentPresenter::comment($row ?? $current),
            'policies' => [CommentPresenter::policy($id, $owner, $admin)],
        ]);
    }
}
