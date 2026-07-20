<?php

declare(strict_types=1);

namespace App\Api;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class AuthConfigAction
{
    public function __invoke(
        ConnectionInterface $database,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $team = $database->createCommand(
            <<<'SQL'
            SELECT id, name, avatar_url, preferences
            FROM teams
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
            SQL,
        )->queryOne();

        if (!is_array($team)) {
            return $responseFactory->createResponse([
                'data' => [
                    'providers' => [],
                    'installationRequired' => true,
                ],
            ]);
        }

        $preferences = [];
        if (is_string($team['preferences'] ?? null) && $team['preferences'] !== '') {
            $preferences = json_decode($team['preferences'], true) ?: [];
        }

        return $responseFactory->createResponse([
            'data' => [
                'name' => $team['name'],
                'customTheme' => $preferences['customTheme'] ?? null,
                'logo' => $team['avatar_url'] ?: null,
                'providers' => [
                    [
                        'id' => 'email',
                        'name' => 'Email',
                        'authUrl' => '/auth/email',
                    ],
                ],
                'installationRequired' => false,
            ],
        ]);
    }
}
