<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\OutlinePresenter;
use App\Api\Shared\PolicyPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Document\DocumentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class DocumentsRestoreAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        DocumentService $documents,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $id = RequestData::string(RequestData::body($request), 'id');
        $row = $id === null ? null : $documents->restore(
            (string) $auth['team_id'],
            (string) $auth['user_id'],
            $id,
        );
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Document not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => OutlinePresenter::document($row),
            'policies' => [PolicyPresenter::document((string) $row['id'])],
        ]);
    }
}
