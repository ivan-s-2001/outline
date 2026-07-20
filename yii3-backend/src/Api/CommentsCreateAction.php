<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\CommentPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Comment\CommentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class CommentsCreateAction
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
        $documentId = RequestData::string($body, 'documentId');
        $data = $body['data'] ?? $body['text'] ?? null;
        if ($documentId === null || $data === null || $data === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'documentId and data are required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $row = $comments->create(
                (string) $auth['team_id'],
                $documentId,
                (string) $auth['user_id'],
                $data,
                RequestData::nullableString($body, 'parentCommentId'),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        return $responseFactory->createResponse([
            'data' => CommentPresenter::comment($row),
            'policies' => [CommentPresenter::policy((string) $row['id'], true, ($auth['role'] ?? null) === 'admin')],
        ])->withStatus(Status::CREATED);
    }
}
