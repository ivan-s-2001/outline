<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Api\Shared\SharePresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Share\ShareService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class SharesCreateAction
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
        try {
            $row = $shares->create(
                (string) $auth['team_id'],
                (string) $auth['user_id'],
                RequestData::nullableString($body, 'documentId'),
                RequestData::nullableString($body, 'collectionId'),
                RequestData::bool($body, 'includeChildDocuments', true),
                RequestData::bool($body, 'allowIndexing', false),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        return $responseFactory->createResponse([
            'data' => SharePresenter::share($row),
            'policies' => [SharePresenter::policy((string) $row['id'], true)],
        ])->withStatus(Status::CREATED);
    }
}
