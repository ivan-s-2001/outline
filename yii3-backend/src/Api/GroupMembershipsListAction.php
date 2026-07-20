<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Shared\GroupPresenter;
use App\Api\Shared\MembershipPresenter;
use App\Api\Shared\RequestData;
use App\Domain\Auth\SessionService;
use App\Domain\Membership\MembershipService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;

final readonly class GroupMembershipsListAction
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

        $body = RequestData::body($request);
        try {
            $rows = $memberships->listGroups(
                (string) $auth['team_id'],
                RequestData::nullableString($body, 'collectionId'),
                RequestData::nullableString($body, 'documentId'),
                RequestData::nullableString($body, 'groupId'),
            );
        } catch (RuntimeException $exception) {
            return $responseFactory->createResponse(['error' => 'validation_error', 'message' => $exception->getMessage()])
                ->withStatus(Status::UNPROCESSABLE_ENTITY);
        }

        $admin = ($auth['role'] ?? null) === 'admin';
        $groups = [];
        foreach ($rows as $row) {
            $groups[(string) $row['group_id']] = GroupPresenter::group([
                'id' => $row['group_id'],
                'name' => $row['group_name'] ?? '',
                'external_id' => $row['group_external_id'] ?? null,
            ]);
        }

        return $responseFactory->createResponse([
            'data' => [
                'groups' => array_values($groups),
                'memberships' => array_map(MembershipPresenter::group(...), $rows),
            ],
            'policies' => array_map(
                static fn(array $row): array => MembershipPresenter::policy((string) $row['id'], $admin),
                $rows,
            ),
        ]);
    }
}
