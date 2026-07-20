<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class SessionService
{
    private const COOKIE_NAME = 'accessToken';
    private const TTL = 2_592_000;

    public function __construct(private ConnectionInterface $database) {}

    /**
     * @return array{token:string, expiresAt:DateTimeImmutable}
     */
    public function create(string $userId, ?string $ip = null, ?string $userAgent = null): array
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable())->modify('+' . self::TTL . ' seconds');

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO sessions (id, user_id, ip, user_agent, expires_at)
            VALUES (:id, :user_id, :ip, :user_agent, :expires_at)
            SQL,
            [
                ':id' => $tokenHash,
                ':user_id' => $userId,
                ':ip' => $ip,
                ':user_agent' => $userAgent,
                ':expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
            ],
        )->execute();

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function authenticate(ServerRequestInterface $request): ?array
    {
        $token = $this->extractToken($request);
        if ($token === null || strlen($token) !== 64) {
            return null;
        }

        $row = $this->database->createCommand(
            <<<'SQL'
            SELECT
                u.id AS user_id,
                u.team_id,
                u.email,
                u.name AS user_name,
                u.role,
                u.state,
                u.avatar_url,
                u.language,
                u.preferences AS user_preferences,
                t.name AS team_name,
                t.subdomain,
                t.avatar_url AS team_avatar_url,
                t.color AS team_color,
                t.preferences AS team_preferences,
                s.expires_at
            FROM sessions s
            INNER JOIN users u ON u.id = s.user_id
            INNER JOIN teams t ON t.id = u.team_id
            WHERE s.id = :id
              AND s.expires_at > UTC_TIMESTAMP(6)
              AND u.deleted_at IS NULL
              AND u.suspended_at IS NULL
              AND u.state = 'active'
              AND t.deleted_at IS NULL
            LIMIT 1
            SQL,
            [':id' => hash('sha256', $token)],
        )->queryOne();

        if (!is_array($row)) {
            return null;
        }

        $this->database->createCommand(
            'UPDATE sessions SET updated_at = UTC_TIMESTAMP(6) WHERE id = :id',
            [':id' => hash('sha256', $token)],
        )->execute();

        return $row;
    }

    public function delete(ServerRequestInterface $request): void
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return;
        }

        $this->database->createCommand(
            'DELETE FROM sessions WHERE id = :id',
            [':id' => hash('sha256', $token)],
        )->execute();
    }

    public function cookieHeader(string $token, DateTimeImmutable $expiresAt): string
    {
        return sprintf(
            '%s=%s; Path=/; Expires=%s; Max-Age=%d; Secure; HttpOnly; SameSite=Lax',
            self::COOKIE_NAME,
            $token,
            $expiresAt->format('D, d M Y H:i:s') . ' GMT',
            self::TTL,
        );
    }

    public function expiredCookieHeader(): string
    {
        return self::COOKIE_NAME . '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; Secure; HttpOnly; SameSite=Lax';
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $authorization = trim($request->getHeaderLine('Authorization'));
        if (str_starts_with($authorization, 'Bearer ')) {
            $token = trim(substr($authorization, 7));
            return $token === '' ? null : $token;
        }

        $cookies = $request->getCookieParams();
        $token = $cookies[self::COOKIE_NAME] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
