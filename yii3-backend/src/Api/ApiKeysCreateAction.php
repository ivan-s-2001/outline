<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\ApiKeyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\ApiKey\ApiKeyService;
use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class ApiKeysCreateAction
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

        $body = RequestData::body($request);
        $name = RequestData::string($body, 'name');
        if ($name === null || $name === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'name is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $apiKeys->create(
                (string) $auth['user_id'],
                $name,
                $body['scope'] ?? null,
                RequestData::nullableString($body, 'expiresAt'),
            );
        } catch (Throwable $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $presented = ApiKeyPresenter::apiKey($result['row']);
        $presented['secret'] = $result['secret'];

        return $responseFactory->createResponse([
            'data' => $presented,
            'policies' => [ApiKeyPresenter::policy((string) $result['row']['id'])],
        ])->withStatus(Status::CREATED);
    }
}
