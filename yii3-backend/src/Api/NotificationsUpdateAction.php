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

final readonly class NotificationsUpdateAction
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
        $ids = isset($body['ids']) && is_array($body['ids']) ? array_values(array_map('strval', $body['ids'])) : [];
        if ($id !== null) {
            $ids[] = $id;
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'id or ids are required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $sets = [];
        $params = [':user_id' => $auth['user_id']];
        if (array_key_exists('viewed', $body)) {
            $sets[] = 'viewed_at = :viewed_at';
            $params[':viewed_at'] = RequestData::bool($body, 'viewed') ? gmdate('Y-m-d H:i:s.u') : null;
        }
        if (array_key_exists('archived', $body)) {
            $sets[] = 'archived_at = :archived_at';
            $params[':archived_at'] = RequestData::bool($body, 'archived') ? gmdate('Y-m-d H:i:s.u') : null;
        }
        if ($sets === []) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'No changes supplied'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $placeholders = [];
        foreach ($ids as $index => $notificationId) {
            $placeholder = ':id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $notificationId;
        }
        $database->createCommand(
            'UPDATE notifications SET ' . implode(', ', $sets)
            . ' WHERE user_id = :user_id AND id IN (' . implode(',', $placeholders) . ')',
            $params,
        )->execute();

        return $responseFactory->createResponse(['success' => true]);
    }
}
