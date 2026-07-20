<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class StarsCreateAction
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
        $documentId = RequestData::nullableString($body, 'documentId');
        $collectionId = RequestData::nullableString($body, 'collectionId');
        if (($documentId === null) === ($collectionId === null)) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Specify exactly one target'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $id = Uuid::uuid4()->toString();
        $index = RequestData::string($body, 'index', 'z' . bin2hex(random_bytes(5)));
        $database->createCommand(
            <<<'SQL'
            INSERT INTO stars (id, user_id, document_id, collection_id, index_key, created_at)
            VALUES (:id, :user_id, :document_id, :collection_id, :index_key, UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE index_key = VALUES(index_key)
            SQL,
            [
                ':id' => $id,
                ':user_id' => $auth['user_id'],
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
                ':index_key' => $index,
            ],
        )->execute();

        $row = $database->createCommand(
            <<<'SQL'
            SELECT * FROM stars
            WHERE user_id = :user_id
              AND ((document_id = :document_id) OR (collection_id = :collection_id))
            LIMIT 1
            SQL,
            [
                ':user_id' => $auth['user_id'],
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
            ],
        )->queryOne();

        return $responseFactory->createResponse(['data' => [
            'id' => (string) $row['id'],
            'userId' => (string) $row['user_id'],
            'documentId' => $row['document_id'] ?? null,
            'collectionId' => $row['collection_id'] ?? null,
            'index' => $row['index_key'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
        ]])->withStatus(Status::CREATED);
    }
}
