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
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class DocumentsInfoAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        DocumentService $documents,
        ConnectionInterface $database,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $id = RequestData::string($body, 'id') ?? RequestData::string($body, 'urlId');
        $row = $id === null ? null : $documents->find((string) $auth['team_id'], $id);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Document not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        $database->createCommand(
            <<<'SQL'
            INSERT INTO views (document_id, user_id, ip, created_at, updated_at)
            VALUES (:document_id, :user_id, :ip, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE updated_at = UTC_TIMESTAMP(6), ip = VALUES(ip)
            SQL,
            [
                ':document_id' => $row['id'],
                ':user_id' => $auth['user_id'],
                ':ip' => ($request->getServerParams()['REMOTE_ADDR'] ?? null),
            ],
        )->execute();

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::document($row),
            'policies' => [PolicyPresenter::document((string) $row['id'])],
        ]);
    }
}
