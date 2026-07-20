<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\ApiKeyPresenter;
use App\Domain\ApiKey\ApiKeyService;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class ApiKeysListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        ApiKeyService $apiKeys,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $rows = $apiKeys->list((string) $auth['user_id']);
        return $responseFactory->createResponse([
            'data' => array_map(ApiKeyPresenter::apiKey(...), $rows),
            'policies' => array_map(
                static fn(array $row): array => ApiKeyPresenter::policy((string) $row['id']),
                $rows,
            ),
        ]);
    }
}
