<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Collection\CollectionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class CollectionsUpdateAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        CollectionService $collections,
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

        $changes = $body;
        unset($changes['id']);
        if (isset($changes['permission']) && !in_array($changes['permission'], ['read', 'read_write', 'admin'], true)) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Invalid permission'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        if (array_key_exists('archived', $changes)) {
            $collections->archive((string) $auth['team_id'], $id, RequestData::bool($changes, 'archived'));
            unset($changes['archived']);
        }

        $row = $collections->update((string) $auth['team_id'], $id, $changes);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Collection not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::collection($row),
            'policies' => [PolicyPresenter::collection((string) $row['id'], (string) $row['permission'])],
        ]);
    }
}
