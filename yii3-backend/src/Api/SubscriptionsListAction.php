<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Api\Shared\SubscriptionPresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Subscription\SubscriptionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class SubscriptionsListAction
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
        $rows = $subscriptions->list(
            (string) $auth['team_id'],
            (string) $auth['user_id'],
            RequestData::nullableString($body, 'documentId'),
            RequestData::nullableString($body, 'collectionId'),
        );

        return $responseFactory->createResponse([
            'data' => array_map(SubscriptionPresenter::subscription(...), $rows),
            'policies' => array_map(
                static fn(array $row): array => SubscriptionPresenter::policy((string) $row['id']),
                $rows,
            ),
        ]);
    }
}
