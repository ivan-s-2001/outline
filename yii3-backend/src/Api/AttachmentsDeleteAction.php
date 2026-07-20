<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Attachment\AttachmentService;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class AttachmentsDeleteAction
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

        $id = RequestData::string(RequestData::body($request), 'id');
        $row = $id === null ? null : $attachments->find($id);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Attachment not found'])
                ->withStatus(Status::NOT_FOUND);
        }
        if ((string) $row['team_id'] !== (string) $auth['team_id']
            || ((string) ($row['user_id'] ?? '') !== (string) $auth['user_id'] && ($auth['role'] ?? null) !== 'admin')) {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $attachments->delete($id);
        return $responseFactory->createResponse(['success' => true]);
    }
}
