<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\AttachmentPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Attachment\AttachmentService;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class AttachmentsCreateAction
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
        $name = RequestData::string($body, 'name');
        $contentType = RequestData::string($body, 'contentType', 'application/octet-stream')
            ?? 'application/octet-stream';
        $size = RequestData::int($body, 'size', -1);
        $preset = RequestData::string($body, 'preset', 'documentAttachment') ?? 'documentAttachment';
        if ($name === null || $name === '' || $size < 0) {
            return $responseFactory->createResponse([
                'error' => 'validation_error',
                'message' => 'name, contentType and size are required',
            ])->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $row = $attachments->create(
                (string) $auth['team_id'],
                (string) $auth['user_id'],
                $name,
                $contentType,
                $size,
                RequestData::nullableString($body, 'documentId'),
                $preset,
                RequestData::nullableString($body, 'id'),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse([
                'error' => 'validation_error',
                'message' => $exception->getMessage(),
            ])->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        return $responseFactory->createResponse([
            'data' => [
                'mode' => 'post',
                'uploadUrl' => '/api/attachments.upload',
                'form' => [
                    'attachmentId' => (string) $row['id'],
                    'Content-Type' => (string) $row['content_type'],
                ],
                'attachment' => AttachmentPresenter::attachment($row),
            ],
        ])->withStatus(Status::CREATED);
    }
}
