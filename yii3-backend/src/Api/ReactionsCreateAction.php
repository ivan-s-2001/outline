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

final readonly class ReactionsCreateAction
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
        $commentId = RequestData::string($body, 'commentId');
        $emoji = RequestData::string($body, 'emoji');
        if ($commentId === null || $emoji === null || $emoji === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'commentId and emoji are required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $row = $comments->addReaction(
            (string) $auth['team_id'],
            $commentId,
            (string) $auth['user_id'],
            mb_substr($emoji, 0, 64),
        );
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Comment not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse(['data' => CommentPresenter::reaction($row)])
            ->withStatus(Status::CREATED);
    }
}
