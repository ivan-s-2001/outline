<?php

declare(strict_types=1);

namespace App\Api;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

final readonly class InstallationInfoAction
{
    public function __invoke(DataResponseFactoryInterface $responseFactory): ResponseInterface
    {
        return $responseFactory->createResponse([
            'data' => [
                'version' => '0.1.0-yii3',
                'latestVersion' => null,
                'versionsBehind' => 0,
            ],
            'policies' => [],
        ]);
    }
}
