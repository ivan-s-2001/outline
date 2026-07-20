<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class StarsDeleteAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        ConnectionInterface $database,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $id = RequestData::nullableString($body, 'id');
        $documentId = RequestData::nullableString($body, 'documentId');
        $collectionId = RequestData::nullableString($body, 'collectionId');

        $where = ['user_id = :user_id'];
        $params = [':user_id' => $auth['user_id']];
        if ($id !== null) {
            $where[] = 'id = :id';
            $params[':id'] = $id;
        } elseif ($documentId !== null) {
            $where[] = 'document_id = :document_id';
            $params[':document_id'] = $documentId;
        } elseif ($collectionId !== null) {
            $where[] = 'collection_id = :collection_id';
            $params[':collection_id'] = $collectionId;
        } else {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'A target is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $database->createCommand('DELETE FROM stars WHERE ' . implode(' AND ', $where), $params)->execute();
        return $responseFactory->createResponse(['success' => true]);
    }
}
