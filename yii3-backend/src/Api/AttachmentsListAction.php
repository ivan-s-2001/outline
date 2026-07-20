<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\AttachmentPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Attachment\AttachmentService;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class AttachmentsListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        AttachmentService $attachments,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $offset = max(0, RequestData::int($body, 'offset'));
        $limit = max(1, min(100, RequestData::int($body, 'limit', 25)));
        $rows = $attachments->list(
            (string) $auth['team_id'],
            (string) $auth['user_id'],
            ($auth['role'] ?? null) === 'admin',
            RequestData::nullableString($body, 'documentId'),
            RequestData::nullableString($body, 'userId'),
            $offset,
            $limit,
        );
        $admin = ($auth['role'] ?? null) === 'admin';

        return $responseFactory->createResponse([
            'data' => array_map(AttachmentPresenter::attachment(...), $rows),
            'policies' => array_map(
                static fn(array $row): array => AttachmentPresenter::policy(
                    (string) $row['id'],
                    (string) ($row['user_id'] ?? '') === (string) $auth['user_id'],
                    $admin,
                ),
                $rows,
            ),
            'pagination' => ['offset' => $offset, 'limit' => $limit, 'total' => count($rows)],
        ]);
    }
}
