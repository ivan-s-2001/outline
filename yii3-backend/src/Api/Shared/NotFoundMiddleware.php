<?php

declare(strict_types=1);

namespace App\Api\Shared;

use App\Web\AppRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class NotFoundMiddleware implements MiddlewareInterface
{
    public function __construct(
        private DataResponseFactoryInterface $dataResponseFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private AppRenderer $appRenderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (strtoupper($request->getMethod()) === 'GET' && !str_starts_with($path, '/api/')) {
            $rootShareId = null;
            if (preg_match('~^/(?:share|s)/([^/]+)~', $path, $matches) === 1) {
                $rootShareId = rawurldecode($matches[1]);
            }

            return $this->appRenderer->render(
                $request,
                $this->responseFactory,
                $this->streamFactory,
                $rootShareId,
            );
        }

        return $this->dataResponseFactory
            ->createResponse([
                'error' => 'not_found',
                'message' => 'Endpoint not found',
            ])
            ->withStatus(Status::NOT_FOUND);
    }
}
