<?php

declare(strict_types=1);

namespace App\Api;

use App\Domain\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

final readonly class AuthDeleteAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $sessions->delete($request);

        return $responseFactory->createResponse([
            'success' => true,
        ])->withHeader('Set-Cookie', $sessions->expiredCookieHeader());
    }
}
