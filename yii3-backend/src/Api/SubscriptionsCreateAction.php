<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Api\Shared\SubscriptionPresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Subscription\SubscriptionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class SubscriptionsCreateAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        SubscriptionService $subscriptions,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $eventType = RequestData::string($body, 'eventType');
        if ($eventType === null || $eventType === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'eventType is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $row = $subscriptions->create(
                (string) $auth['team_id'],
                (string) $auth['user_id'],
                RequestData::nullableString($body, 'documentId'),
                RequestData::nullableString($body, 'collectionId'),
                mb_substr($eventType, 0, 100),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        return $responseFactory->createResponse([
            'data' => SubscriptionPresenter::subscription($row),
            'policies' => [SubscriptionPresenter::policy((string) $row['id'])],
        ])->withStatus(Status::CREATED);
    }
}
