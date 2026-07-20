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
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class DocumentsUpdateAction
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
        if ($id === null || $id === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'id is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $expectedRevision = array_key_exists('revision', $body) ? (int) $body['revision'] : null;
        $changes = $body;
        unset($changes['id'], $changes['revision']);
        if (array_key_exists('data', $changes) && !array_key_exists('content', $changes)) {
            $changes['content'] = $changes['data'];
        }
        unset($changes['data']);

        $result = $documents->update(
            (string) $auth['team_id'],
            (string) $auth['user_id'],
            $id,
            $changes,
            $expectedRevision,
        );

        if ($result['document'] === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Document not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        $presented = OutlinePresenter::document($result['document']);
        if ($result['conflict']) {
            return $responseFactory->createResponse([
                'error' => 'revision_conflict',
                'message' => 'Document was updated by another client',
                'data' => $presented,
            ])->withStatus(Status::CONFLICT);
        }

        return $responseFactory->createResponse([
            'data' => $presented,
            'policies' => [PolicyPresenter::document($id)],
        ]);
    }
}
