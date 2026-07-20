<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use RuntimeException;

final class TokenService
{
    public function collaborationToken(string $userId, string $teamId): string
    {
        $secret = getenv('APP_SECRET');
        if ($secret === false || strlen($secret) < 32) {
            throw new RuntimeException('APP_SECRET must contain at least 32 characters.');
        }

        $now = time();
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub' => $userId,
            'userId' => $userId,
            'teamId' => $teamId,
            'iat' => $now,
            'exp' => $now + 3600,
            'aud' => 'collaboration',
        ];

        $segments = [
            $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
