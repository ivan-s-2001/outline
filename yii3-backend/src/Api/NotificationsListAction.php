<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class NotificationsListAction
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
        $offset = max(0, RequestData::int($body, 'offset'));
        $limit = max(1, min(100, RequestData::int($body, 'limit', 25)));
        $where = ['n.user_id = :user_id'];
        $params = [':user_id' => $auth['user_id']];
        if (RequestData::bool($body, 'unread', false)) {
            $where[] = 'n.viewed_at IS NULL';
        }
        if (!RequestData::bool($body, 'includeArchived', false)) {
            $where[] = 'n.archived_at IS NULL';
        }

        $rows = $database->createCommand(
            'SELECT n.*, '
            . 'a.name AS actor_name, a.email AS actor_email, a.avatar_url AS actor_avatar_url, '
            . 'd.title AS document_title, d.text AS document_text, d.url_id AS document_url_id, '
            . 'd.collection_id AS document_collection_id, d.created_at AS document_created_at, d.updated_at AS document_updated_at '
            . 'FROM notifications n '
            . 'LEFT JOIN users a ON a.id = n.actor_id '
            . 'LEFT JOIN documents d ON d.id = n.document_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY n.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        )->queryAll();

        $notifications = [];
        $users = [];
        $documents = [];
        foreach ($rows as $row) {
            $data = $row['data'] ?? null;
            if (is_string($data) && $data !== '') {
                $data = json_decode($data, true) ?? [];
            }
            $notifications[] = [
                'id' => (string) $row['id'],
                'userId' => (string) $row['user_id'],
                'actorId' => $row['actor_id'] ?? null,
                'eventId' => $row['event_id'] ?? null,
                'documentId' => $row['document_id'] ?? null,
                'commentId' => $row['comment_id'] ?? null,
                'type' => (string) $row['type'],
                'data' => $data,
                'viewedAt' => $row['viewed_at'] ?? null,
                'archivedAt' => $row['archived_at'] ?? null,
                'createdAt' => $row['created_at'] ?? null,
            ];
            if ($row['actor_id'] !== null) {
                $users[(string) $row['actor_id']] = OutlinePresenter::user([
                    'id' => $row['actor_id'],
                    'team_id' => $auth['team_id'],
                    'name' => $row['actor_name'] ?? '',
                    'email' => $row['actor_email'] ?? '',
                    'avatar_url' => $row['actor_avatar_url'] ?? null,
                    'role' => 'member',
                    'state' => 'active',
                ]);
            }
            if ($row['document_id'] !== null && $row['document_title'] !== null) {
                $documents[(string) $row['document_id']] = OutlinePresenter::document([
                    'id' => $row['document_id'],
                    'title' => $row['document_title'],
                    'text' => $row['document_text'],
                    'url_id' => $row['document_url_id'],
                    'collection_id' => $row['document_collection_id'],
                    'created_at' => $row['document_created_at'],
                    'updated_at' => $row['document_updated_at'],
                ]);
            }
        }

        return $responseFactory->createResponse([
            'data' => [
                'notifications' => $notifications,
                'users' => array_values($users),
                'documents' => array_values($documents),
            ],
            'pagination' => ['offset' => $offset, 'limit' => $limit],
        ]);
    }
}
