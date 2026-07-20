<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class PinsCreateAction
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
        $documentId = RequestData::string($body, 'documentId');
        $collectionId = RequestData::nullableString($body, 'collectionId');
        if ($documentId === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'documentId is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $id = Uuid::uuid4()->toString();
        try {
            $database->createCommand(
                <<<'SQL'
                INSERT INTO pins (id, team_id, collection_id, document_id, created_by_id, index_key, created_at)
                VALUES (:id, :team_id, :collection_id, :document_id, :created_by_id, :index_key, UTC_TIMESTAMP(6))
                ON DUPLICATE KEY UPDATE index_key = VALUES(index_key)
                SQL,
                [
                    ':id' => $id,
                    ':team_id' => $auth['team_id'],
                    ':collection_id' => $collectionId,
                    ':document_id' => $documentId,
                    ':created_by_id' => $auth['user_id'],
                    ':index_key' => RequestData::string($body, 'index', 'z' . bin2hex(random_bytes(5))),
                ],
            )->execute();
        } catch (Throwable) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Invalid document or collection'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $row = $database->createCommand(
            <<<'SQL'
            SELECT * FROM pins
            WHERE team_id = :team_id AND document_id = :document_id
              AND ((collection_id = :collection_id) OR (collection_id IS NULL AND :collection_id IS NULL))
            LIMIT 1
            SQL,
            [
                ':team_id' => $auth['team_id'],
                ':document_id' => $documentId,
                ':collection_id' => $collectionId,
            ],
        )->queryOne();

        return $responseFactory->createResponse(['data' => [
            'id' => (string) $row['id'],
            'documentId' => (string) $row['document_id'],
            'collectionId' => $row['collection_id'] ?? null,
            'index' => $row['index_key'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
        ]])->withStatus(Status::CREATED);
    }
}
