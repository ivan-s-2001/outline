<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Api\Shared\SharePresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Share\ShareService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class SharesListAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        ShareService $shares,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $offset = max(0, RequestData::int($body, 'offset'));
        $limit = max(1, min(100, RequestData::int($body, 'limit', 25)));
        $rows = $shares->list(
            (string) $auth['team_id'],
            RequestData::nullableString($body, 'query'),
            $offset,
            $limit,
        );
        $admin = ($auth['role'] ?? null) === 'admin';

        return $responseFactory->createResponse([
            'data' => array_map(SharePresenter::share(...), $rows),
            'policies' => array_map(
                static fn(array $row): array => SharePresenter::policy(
                    (string) $row['id'],
                    $admin || (string) ($row['created_by_id'] ?? '') === (string) $auth['user_id'],
                ),
                $rows,
            ),
            'pagination' => ['offset' => $offset, 'limit' => $limit, 'total' => count($rows)],
        ]);
    }
}
