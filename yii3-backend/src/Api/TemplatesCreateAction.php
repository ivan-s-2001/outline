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

final readonly class TemplatesCreateAction
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
        $name = RequestData::string($body, 'name');
        if ($name === null || $name === '') {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'name is required'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }
        $body['name'] = $name;
        $row = $templates->create((string) $auth['team_id'], (string) $auth['user_id'], $body);

        return $responseFactory->createResponse([
            'data' => TemplatePresenter::template($row),
            'policies' => [TemplatePresenter::policy((string) $row['id'])],
        ])->withStatus(Status::CREATED);
    }
}
