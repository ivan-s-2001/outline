<?php

declare(strict_types=1);

namespace App\Domain\ApiKey;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class ApiKeyService
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return list<array<string, mixed>> */
    public function list(string $userId): array
    {
        return $this->database->createCommand(
            <<<'SQL'
            SELECT * FROM api_keys
            WHERE user_id = :user_id AND revoked_at IS NULL
            ORDER BY created_at DESC
            SQL,
            [':user_id' => $userId],
        )->queryAll();
    }

    /** @return array{row:array<string,mixed>,secret:string} */
    public function create(string $userId, string $name, mixed $scope, ?string $expiresAt): array
    {
        $id = Uuid::uuid4()->toString();
        $secret = 'ol_api_' . rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
        $scopeJson = $scope === null || is_string($scope)
            ? $scope
            : json_encode($scope, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($expiresAt !== null && $expiresAt !== '') {
            $expiresAt = (new DateTimeImmutable($expiresAt))->format('Y-m-d H:i:s.u');
        } else {
            $expiresAt = null;
        }

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO api_keys (
                id, user_id, name, secret_hash, last4, scope, expires_at, created_at
            ) VALUES (
                :id, :user_id, :name, :secret_hash, :last4, :scope, :expires_at, UTC_TIMESTAMP(6)
            )
            SQL,
            [
                ':id' => $id,
                ':user_id' => $userId,
                ':name' => $name,
                ':secret_hash' => hash('sha256', $secret),
                ':last4' => substr($secret, -4),
                ':scope' => $scopeJson,
                ':expires_at' => $expiresAt,
            ],
        )->execute();

        $row = $this->database->createCommand(
            'SELECT * FROM api_keys WHERE id = :id LIMIT 1',
            [':id' => $id],
        )->queryOne();
        if (!is_array($row)) {
            throw new RuntimeException('Created API key was not found.');
        }

        return ['row' => $row, 'secret' => $secret];
    }

    public function revoke(string $userId, string $id): bool
    {
        return $this->database->createCommand(
            <<<'SQL'
            UPDATE api_keys
            SET revoked_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL
            SQL,
            [':id' => $id, ':user_id' => $userId],
        )->execute() > 0;
    }

    /** @return array<string,mixed>|null */
    public function authenticate(string $secret): ?array
    {
        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT k.*, u.team_id, u.email, u.name AS user_name, u.role, u.state,
                   u.avatar_url, u.language, u.preferences AS user_preferences,
                   t.name AS team_name, t.subdomain, t.avatar_url AS team_avatar_url,
                   t.color AS team_color, t.preferences AS team_preferences
            FROM api_keys k
            INNER JOIN users u ON u.id = k.user_id
            INNER JOIN teams t ON t.id = u.team_id
            WHERE k.secret_hash = :secret_hash
              AND k.revoked_at IS NULL
              AND (k.expires_at IS NULL OR k.expires_at > UTC_TIMESTAMP(6))
              AND u.deleted_at IS NULL
              AND u.suspended_at IS NULL
              AND u.state = 'active'
              AND t.deleted_at IS NULL
            LIMIT 1
            SQL,
            [':secret_hash' => hash('sha256', $secret)],
        )->queryOne();

        if (!is_array($row)) {
            return null;
        }

        $this->database->createCommand(
            'UPDATE api_keys SET last_active_at = UTC_TIMESTAMP(6) WHERE id = :id',
            [':id' => $row['id']],
        )->execute();

        $row['user_id'] = $row['user_id'];
        return $row;
    }
}
