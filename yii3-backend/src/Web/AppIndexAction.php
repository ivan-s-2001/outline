<?php

declare(strict_types=1);

namespace App\Web;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class AppIndexAction
{
    public function __invoke(
        ServerRequestInterface $request,
        AppRenderer $renderer,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): ResponseInterface {
        return $renderer->render(
            $request,
            $responseFactory,
            $streamFactory,
            $this->shareId($request->getUri()->getPath()),
        );
    }

    private function shareId(string $path): ?string
    {
        if (preg_match('~^/(?:share|s)/([^/]+)~', $path, $matches) === 1) {
            return rawurldecode($matches[1]);
        }

        return null;
    }
}
