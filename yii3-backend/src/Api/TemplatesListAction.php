<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\TemplatePresenter;
use App\Domain\Auth\SessionService;
use App\Domain\Template\TemplateService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class TemplatesListAction
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

        $rows = $templates->list((string) $auth['team_id']);
        return $responseFactory->createResponse([
            'data' => array_map(TemplatePresenter::template(...), $rows),
            'policies' => array_map(
                static fn(array $row): array => TemplatePresenter::policy((string) $row['id']),
                $rows,
            ),
            'pagination' => ['offset' => 0, 'limit' => count($rows)],
        ]);
    }
}
