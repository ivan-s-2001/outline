<?php

declare(strict_types=1);

namespace App\Api\Shared;

final class PolicyPresenter
{
    /** @return array{id:string, abilities:array<string, bool|array>} */
    public static function team(string $teamId, bool $admin): array
    {
        return [
            'id' => $teamId,
            'abilities' => [
                'read' => true,
                'update' => $admin,
                'delete' => $admin,
                'createCollection' => true,
                'createGroup' => $admin,
                'createTemplate' => true,
                'createUser' => $admin,
                'manage' => $admin,
                'manageApiKeys' => $admin,
                'manageAuthentication' => $admin,
                'manageIntegrations' => $admin,
                'manageSecurity' => $admin,
                'manageUsers' => $admin,
            ],
        ];
    }

    /** @return array{id:string, abilities:array<string, bool|array>} */
    public static function user(string $userId, bool $self, bool $admin): array
    {
        return [
            'id' => $userId,
            'abilities' => [
                'read' => true,
                'update' => $self || $admin,
                'delete' => $self || $admin,
                'suspend' => $admin && !$self,
                'activate' => $admin,
            ],
        ];
    }

    /** @return array{id:string, abilities:array<string, bool|array>} */
    public static function collection(string $collectionId, string $permission): array
    {
        $write = in_array($permission, ['read_write', 'admin'], true);
        $admin = $permission === 'admin';

        return [
            'id' => $collectionId,
            'abilities' => [
                'read' => true,
                'update' => $write,
                'delete' => $admin,
                'archive' => $write,
                'createDocument' => $write,
                'share' => $write,
                'manageUsers' => $admin,
            ],
        ];
    }

    /** @return array{id:string, abilities:array<string, bool|array>} */
    public static function document(string $documentId, bool $write = true): array
    {
        return [
            'id' => $documentId,
            'abilities' => [
                'read' => true,
                'update' => $write,
                'delete' => $write,
                'archive' => $write,
                'restore' => $write,
                'publish' => $write,
                'move' => $write,
                'duplicate' => $write,
                'comment' => true,
                'share' => $write,
            ],
        ];
    }
}
