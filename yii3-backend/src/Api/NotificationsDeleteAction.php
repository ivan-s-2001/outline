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

final readonly class NotificationsDeleteAction
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

        $id = RequestData::string(RequestData::body($request), 'id');
        if ($id === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'id is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $database->createCommand(
            'DELETE FROM notifications WHERE id = :id AND user_id = :user_id',
            [':id' => $id, ':user_id' => $auth['user_id']],
        )->execute();

        return $responseFactory->createResponse(['success' => true]);
    }
}
