<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\CommentPresenter;
use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Comment\CommentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class CommentsListAction
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

        $documentId = RequestData::string(RequestData::body($request), 'documentId');
        if ($documentId === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'documentId is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $rows = $comments->list((string) $auth['team_id'], $documentId);
        $commentIds = array_map(static fn(array $row): string => (string) $row['id'], $rows);
        $reactionRows = $comments->reactions((string) $auth['team_id'], $commentIds);
        $admin = ($auth['role'] ?? null) === 'admin';
        $data = [];
        $policies = [];
        $users = [];
        foreach ($rows as $row) {
            $data[] = CommentPresenter::comment($row);
            $policies[] = CommentPresenter::policy(
                (string) $row['id'],
                (string) ($row['user_id'] ?? '') === (string) $auth['user_id'],
                $admin,
            );
            if ($row['user_id'] !== null) {
                $users[(string) $row['user_id']] = OutlinePresenter::user([
                    'id' => $row['user_id'],
                    'team_id' => $auth['team_id'],
                    'name' => $row['user_name'] ?? '',
                    'email' => $row['user_email'] ?? '',
                    'avatar_url' => $row['user_avatar_url'] ?? null,
                    'role' => 'member',
                    'state' => 'active',
                ]);
            }
        }

        return $responseFactory->createResponse([
            'data' => [
                'comments' => $data,
                'reactions' => array_map(CommentPresenter::reaction(...), $reactionRows),
                'users' => array_values($users),
            ],
            'policies' => $policies,
        ]);
    }
}
