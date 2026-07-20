<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\AttachmentPresenter;
use App\Domain\Attachment\AttachmentService;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class AttachmentsUploadAction
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

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $attachmentId = isset($body['attachmentId']) ? trim((string) $body['attachmentId']) : '';
        $row = $attachmentId !== '' ? $attachments->find($attachmentId) : null;
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Attachment not found'])
                ->withStatus(Status::NOT_FOUND);
        }
        if ((string) $row['team_id'] !== (string) $auth['team_id']
            || ((string) ($row['user_id'] ?? '') !== (string) $auth['user_id'] && ($auth['role'] ?? null) !== 'admin')) {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $file = $this->firstFile($request->getUploadedFiles());
        if ($file === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Multipart file is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $stored = $attachments->store($attachmentId, $file);
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        return $responseFactory->createResponse([
            'data' => AttachmentPresenter::attachment($stored),
        ]);
    }

    /** @param array<string|int, UploadedFileInterface|array> $files */
    private function firstFile(array $files): ?UploadedFileInterface
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFileInterface) {
                return $file;
            }
            if (is_array($file)) {
                $nested = $this->firstFile($file);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }
}
