<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Api\Shared\TemplatePresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Template\TemplateService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class TemplatesUpdateAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        TemplateService $templates,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }

        $body = RequestData::body($request);
        $id = RequestData::string($body, 'id');
        if ($id === null) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'id is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }
        unset($body['id']);
        $row = $templates->update((string) $auth['team_id'], $id, $body);
        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Template not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => TemplatePresenter::template($row),
            'policies' => [TemplatePresenter::policy($id)],
        ]);
    }
}
