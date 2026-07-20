<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Document\DocumentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class RevisionsListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        DocumentService $documents,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $documentId = RequestData::string($body, 'documentId');
        if ($documentId === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'documentId is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $rows = $documents->revisions(
            (string) $auth['team_id'],
            $documentId,
            RequestData::int($body, 'limit', 100),
        );

        $data = array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'documentId' => (string) $row['document_id'],
            'userId' => $row['user_id'] ?? null,
            'title' => (string) $row['title'],
            'text' => $row['text'] ?? '',
            'content' => is_string($row['content'] ?? null)
                ? (json_decode($row['content'], true) ?? null)
                : ($row['content'] ?? null),
            'version' => (int) $row['version'],
            'createdAt' => $row['created_at'] ?? null,
        ], $rows);

        return $responseFactory->createResponse([
            'data' => $data,
            'pagination' => ['offset' => 0, 'limit' => count($data)],
        ]);
    }
}
