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

final readonly class DocumentsMoveAction
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
        $id = RequestData::string($body, 'id');
        if ($id === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'id is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $row = $documents->move(
                (string) $auth['team_id'],
                (string) $auth['user_id'],
                $id,
                RequestData::nullableString($body, 'collectionId'),
                RequestData::nullableString($body, 'parentDocumentId'),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse([
                'error' => 'validation_error',
                'message' => $exception->getMessage(),
            ])->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Document not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::document($row),
            'policies' => [PolicyPresenter::document((string) $row['id'])],
        ]);
    }
}
