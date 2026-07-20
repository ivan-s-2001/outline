<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Template\TemplateService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class TemplatesDeleteAction
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

        $id = RequestData::string(RequestData::body($request), 'id');
        if ($id === null || !$templates->delete((string) $auth['team_id'], $id)) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Template not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse(['success' => true]);
    }
}
