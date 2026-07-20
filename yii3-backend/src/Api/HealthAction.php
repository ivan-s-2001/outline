<?php

declare(strict_types=1);

namespace App\Api;

use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Status;

final readonly class HealthAction
{
    public function __invoke(
        ConnectionInterface $database,
        Client $redis,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $healthy = true;

        try {
            $databaseVersion = (string) $database
                ->createCommand('SELECT VERSION()')
                ->queryScalar();
            $databaseStatus = 'ok';
        } catch (Throwable) {
            $healthy = false;
            $databaseVersion = null;
            $databaseStatus = 'error';
        }

        try {
            $redisResponse = $redis->ping();
            $redisStatus = strtoupper((string) $redisResponse) === 'PONG' ? 'ok' : 'error';
            $healthy = $healthy && $redisStatus === 'ok';
        } catch (Throwable) {
            $healthy = false;
            $redisStatus = 'error';
        }

        return $responseFactory
            ->createResponse([
                'status' => $healthy ? 'ok' : 'error',
                'application' => 'Outline Yii 3',
                'php' => PHP_VERSION,
                'database' => [
                    'status' => $databaseStatus,
                    'version' => $databaseVersion,
                ],
                'redis' => [
                    'status' => $redisStatus,
                ],
            ])
            ->withStatus($healthy ? Status::OK : Status::SERVICE_UNAVAILABLE);
    }
}
