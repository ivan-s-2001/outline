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

final readonly class TemplatesInfoAction
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
        $row = $id === null ? null : $templates->find((string) $auth['team_id'], $id);
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
