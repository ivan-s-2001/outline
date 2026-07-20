<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Document\DocumentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class DocumentsCreateAction
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
        $title = RequestData::string($body, 'title', 'Untitled') ?: 'Untitled';
        $text = (string) ($body['text'] ?? '');
        $content = $body['content'] ?? $body['data'] ?? null;

        try {
            $row = $documents->create(
                (string) $auth['team_id'],
                (string) $auth['user_id'],
                RequestData::nullableString($body, 'collectionId'),
                RequestData::nullableString($body, 'parentDocumentId'),
                $title,
                $text,
                $content,
                RequestData::bool($body, 'publish', false),
                RequestData::nullableString($body, 'templateId'),
                RequestData::nullableString($body, 'icon'),
                RequestData::nullableString($body, 'color'),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse([
                'error' => 'validation_error',
                'message' => $exception->getMessage(),
            ])->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::document($row),
            'policies' => [PolicyPresenter::document((string) $row['id'])],
        ])->withStatus(Status::CREATED);
    }
}
