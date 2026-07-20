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

final readonly class EventsListAction
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
        $where = ['e.team_id = :team_id'];
        $params = [':team_id' => $auth['team_id']];
        foreach ([
            'userId' => 'e.user_id',
            'documentId' => 'e.document_id',
            'collectionId' => 'e.collection_id',
            'name' => 'e.name',
        ] as $input => $column) {
            $value = RequestData::nullableString($body, $input);
            if ($value !== null) {
                $placeholder = ':' . $input;
                $where[] = $column . ' = ' . $placeholder;
                $params[$placeholder] = $value;
            }
        }
        $query = RequestData::nullableString($body, 'query');
        if ($query !== null) {
            $where[] = '(e.name LIKE :query OR e.data LIKE :query)';
            $params[':query'] = '%' . addcslashes($query, '%_') . '%';
        }
        if (($auth['role'] ?? null) !== 'admin') {
            $where[] = '(e.user_id = :current_user OR e.document_id IN (SELECT id FROM documents WHERE team_id = :team_id AND deleted_at IS NULL))';
            $params[':current_user'] = $auth['user_id'];
        }

        $rows = $database->createCommand(
            'SELECT e.*, '
            . 'u.name AS actor_name, u.email AS actor_email, u.avatar_url AS actor_avatar_url, '
            . 'd.title AS document_title, d.text AS document_text, d.url_id AS document_url_id, '
            . 'd.collection_id AS document_collection_id, d.created_at AS document_created_at, d.updated_at AS document_updated_at, '
            . 'c.name AS collection_name, c.description AS collection_description, c.permission AS collection_permission, '
            . 'c.document_structure, c.created_at AS collection_created_at, c.updated_at AS collection_updated_at '
            . 'FROM events e '
            . 'LEFT JOIN users u ON u.id = e.user_id '
            . 'LEFT JOIN documents d ON d.id = e.document_id '
            . 'LEFT JOIN collections c ON c.id = e.collection_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY e.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        )->queryAll();

        $events = [];
        $users = [];
        $documents = [];
        $collections = [];
        foreach ($rows as $row) {
            $data = $row['data'] ?? null;
            if (is_string($data) && $data !== '') {
                $data = json_decode($data, true) ?? [];
            }
            $events[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'teamId' => (string) $row['team_id'],
                'userId' => $row['user_id'] ?? null,
                'modelId' => $row['model_id'] ?? null,
                'documentId' => $row['document_id'] ?? null,
                'collectionId' => $row['collection_id'] ?? null,
                'ip' => $row['ip'] ?? null,
                'data' => $data,
                'createdAt' => $row['created_at'] ?? null,
            ];
            if ($row['user_id'] !== null) {
                $users[(string) $row['user_id']] = OutlinePresenter::user([
                    'id' => $row['user_id'],
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
            if ($row['collection_id'] !== null && $row['collection_name'] !== null) {
                $collections[(string) $row['collection_id']] = OutlinePresenter::collection([
                    'id' => $row['collection_id'],
                    'name' => $row['collection_name'],
                    'description' => $row['collection_description'],
                    'permission' => $row['collection_permission'],
                    'document_structure' => $row['document_structure'],
                    'created_at' => $row['collection_created_at'],
                    'updated_at' => $row['collection_updated_at'],
                ]);
            }
        }

        return $responseFactory->createResponse([
            'data' => [
                'events' => $events,
                'users' => array_values($users),
                'documents' => array_values($documents),
                'collections' => array_values($collections),
            ],
            'pagination' => ['offset' => $offset, 'limit' => $limit, 'total' => count($events)],
        ]);
    }
}
