<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Attachment\AttachmentService;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class AttachmentsRedirectAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        AttachmentService $attachments,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        DataResponseFactoryInterface $dataResponseFactory,
    ): ResponseInterface {
        $body = RequestData::body($request);
        $query = $request->getQueryParams();
        $id = isset($query['id']) ? trim((string) $query['id']) : RequestData::string($body, 'id');
        $row = $id !== null && $id !== '' ? $attachments->find($id) : null;
        if ($row === null) {
            return $dataResponseFactory->createResponse(['error' => 'not_found', 'message' => 'Attachment not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        if ((string) $row['acl'] !== 'public-read') {
            $auth = $sessions->authenticate($request);
            if ($auth === null) {
                return $dataResponseFactory->createResponse(['error' => 'authentication_required'])
                    ->withStatus(Status::UNAUTHORIZED);
            }
            if ((string) $row['team_id'] !== (string) $auth['team_id']) {
                return $dataResponseFactory->createResponse(['error' => 'authorization_error'])
                    ->withStatus(Status::FORBIDDEN);
            }
        }

        try {
            $path = $attachments->filePath($row);
        } catch (RuntimeException) {
            return $dataResponseFactory->createResponse(['error' => 'not_found', 'message' => 'Attachment file is missing'])
                ->withStatus(Status::NOT_FOUND);
        }

        $name = str_replace(['"', "\r", "\n"], '', basename((string) $row['name']));
        $response = $responseFactory->createResponse(Status::OK)
            ->withHeader('Content-Type', (string) $row['content_type'])
            ->withHeader('Content-Length', (string) filesize($path))
            ->withHeader(
                'Content-Disposition',
                'inline; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            )
            ->withHeader(
                'Cache-Control',
                (string) $row['acl'] === 'public-read'
                    ? 'public, max-age=604800, immutable'
                    : 'private, max-age=300',
            )
            ->withHeader('X-Content-Type-Options', 'nosniff');

        return $response->withBody($streamFactory->createStreamFromFile($path, 'r'));
    }
}
