<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\MembershipPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Membership\MembershipService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class UserMembershipsUpsertAction
{
    public function __invoke(
        ServerRequestInterface $request,
        SessionService $sessions,
        MembershipService $memberships,
        DataResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $auth = $sessions->authenticate($request);
        if ($auth === null) {
            return $responseFactory->createResponse(['error' => 'authentication_required'])
                ->withStatus(Status::UNAUTHORIZED);
        }
        if (($auth['role'] ?? null) !== 'admin') {
            return $responseFactory->createResponse(['error' => 'authorization_error'])
                ->withStatus(Status::FORBIDDEN);
        }

        $body = RequestData::body($request);
        $permission = RequestData::string($body, 'permission', 'read') ?? 'read';
        if (!in_array($permission, ['read', 'read_write', 'admin'], true)) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => 'Invalid permission'])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        try {
            $id = RequestData::nullableString($body, 'id');
            if ($id !== null) {
                $row = $memberships->updateUser((string) $auth['team_id'], $id, $permission);
            } else {
                $userId = RequestData::string($body, 'userId');
                if ($userId === null) {
                    throw new RuntimeException('userId is required.');
                }
                $row = $memberships->createUser(
                    (string) $auth['team_id'],
                    $userId,
                    RequestData::nullableString($body, 'collectionId'),
                    RequestData::nullableString($body, 'documentId'),
                    $permission,
                );
            }
        } catch (Throwable $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        if ($row === null) {
            return $responseFactory->createResponse(['error' => 'not_found', 'message' => 'Membership not found'])
                ->withStatus(Status::NOT_FOUND);
        }

        return $responseFactory->createResponse([
            'data' => MembershipPresenter::user($row),
            'policies' => [MembershipPresenter::policy((string) $row['id'], true)],
        ]);
    }
}
