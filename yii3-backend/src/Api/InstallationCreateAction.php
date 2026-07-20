<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class InstallationCreateAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ConnectionInterface $database,
        SessionService $sessions,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $teamName = trim((string) ($body['teamName'] ?? ''));
        $userName = trim((string) ($body['userName'] ?? ''));
        $userEmail = mb_strtolower(trim((string) ($body['userEmail'] ?? '')));

        if ($teamName === '' || $userName === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return $responseFactory->createResponse([
                'error' => 'validation_error',
                'message' => 'teamName, userName and a valid userEmail are required',
            ])->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        if ((int) $database->createCommand('SELECT COUNT(*) FROM teams WHERE deleted_at IS NULL')->queryScalar() > 0) {
            return $responseFactory->createResponse([
                'error' => 'validation_error',
                'message' => 'Installation already has an existing workspace',
            ])->withStatus(Status::CONFLICT);
        }

        $teamId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $collectionId = Uuid::uuid4()->toString();
        $documentId = Uuid::uuid4()->toString();
        $revisionId = Uuid::uuid4()->toString();
        $urlId = substr(bin2hex(random_bytes(8)), 0, 12);
        $subdomain = $this->slug($teamName);
        $now = gmdate('Y-m-d H:i:s.u');

        $transaction = $database->beginTransaction();

        try {
            $database->createCommand(
                <<<'SQL'
                INSERT INTO teams (id, name, subdomain, default_user_role, preferences, created_at, updated_at)
                VALUES (:id, :name, :subdomain, 'member', :preferences, :created_at, :updated_at)
                SQL,
                [
                    ':id' => $teamId,
                    ':name' => $teamName,
                    ':subdomain' => $subdomain,
                    ':preferences' => '{}',
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ],
            )->execute();

            $database->createCommand(
                <<<'SQL'
                INSERT INTO users (
                    id, team_id, email, name, role, state, language, preferences, created_at, updated_at
                ) VALUES (
                    :id, :team_id, :email, :name, 'admin', 'active', 'ru_RU', '{}', :created_at, :updated_at
                )
                SQL,
                [
                    ':id' => $userId,
                    ':team_id' => $teamId,
                    ':email' => $userEmail,
                    ':name' => $userName,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ],
            )->execute();

            $database->createCommand(
                <<<'SQL'
                INSERT INTO collections (
                    id, team_id, name, description, permission, color, document_structure,
                    created_by_id, created_at, updated_at
                ) VALUES (
                    :id, :team_id, :name, :description, 'read_write', :color, :structure,
                    :created_by_id, :created_at, :updated_at
                )
                SQL,
                [
                    ':id' => $collectionId,
                    ':team_id' => $teamId,
                    ':name' => 'Документация',
                    ':description' => 'Основная коллекция рабочего пространства',
                    ':color' => '#4E5C6E',
                    ':structure' => json_encode([['id' => $documentId, 'children' => []]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ':created_by_id' => $userId,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ],
            )->execute();

            $welcomeText = "# Добро пожаловать\n\nЭто рабочее пространство Outline на Yii 3 и MariaDB 11.8.";
            $database->createCommand(
                <<<'SQL'
                INSERT INTO documents (
                    id, team_id, collection_id, title, text, content, url_id, index_key,
                    created_by_id, updated_by_id, revision_number, published_at, created_at, updated_at
                ) VALUES (
                    :id, :team_id, :collection_id, :title, :text, NULL, :url_id, :index_key,
                    :created_by_id, :updated_by_id, 1, :published_at, :created_at, :updated_at
                )
                SQL,
                [
                    ':id' => $documentId,
                    ':team_id' => $teamId,
                    ':collection_id' => $collectionId,
                    ':title' => 'Добро пожаловать',
                    ':text' => $welcomeText,
                    ':url_id' => $urlId,
                    ':index_key' => 'a0',
                    ':created_by_id' => $userId,
                    ':updated_by_id' => $userId,
                    ':published_at' => $now,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ],
            )->execute();

            $database->createCommand(
                <<<'SQL'
                INSERT INTO revisions (id, document_id, user_id, title, text, content, version, created_at)
                VALUES (:id, :document_id, :user_id, :title, :text, NULL, 1, :created_at)
                SQL,
                [
                    ':id' => $revisionId,
                    ':document_id' => $documentId,
                    ':user_id' => $userId,
                    ':title' => 'Добро пожаловать',
                    ':text' => $welcomeText,
                    ':created_at' => $now,
                ],
            )->execute();

            $database->createCommand(
                <<<'SQL'
                INSERT INTO events (team_id, user_id, name, model_id, data, created_at)
                VALUES (:team_id, :user_id, 'teams.create', :model_id, :data, :created_at)
                SQL,
                [
                    ':team_id' => $teamId,
                    ':user_id' => $userId,
                    ':model_id' => $teamId,
                    ':data' => json_encode(['name' => $teamName], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ':created_at' => $now,
                ],
            )->execute();

            $server = $request->getServerParams();
            $session = $sessions->create(
                $userId,
                isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : null,
                $request->getHeaderLine('User-Agent') ?: null,
            );

            $transaction->commit();
        } catch (Throwable $exception) {
            if ($transaction->isActive()) {
                $transaction->rollBack();
            }
            throw $exception;
        }

        $authRow = [
            'user_id' => $userId,
            'team_id' => $teamId,
            'email' => $userEmail,
            'user_name' => $userName,
            'role' => 'admin',
            'state' => 'active',
            'language' => 'ru_RU',
            'user_preferences' => '{}',
            'team_name' => $teamName,
            'subdomain' => $subdomain,
            'team_preferences' => '{}',
        ];

        return $responseFactory->createResponse([
            'data' => [
                'user' => OutlinePresenter::user($authRow),
                'team' => OutlinePresenter::team($authRow),
                'collectionId' => $collectionId,
                'documentId' => $documentId,
            ],
            'policies' => [],
        ])->withHeader('Set-Cookie', $sessions->cookieHeader($session['token'], $session['expiresAt']));
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? mb_substr($value, 0, 60) : 'workspace';
    }
}
