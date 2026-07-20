<?php

declare(strict_types=1);

use App\Api\AuthConfigAction;
use App\Api\AuthDeleteAction;
use App\Api\AuthInfoAction;
use App\Api\CollectionsCreateAction;
use App\Api\CollectionsDeleteAction;
use App\Api\CollectionsInfoAction;
use App\Api\CollectionsListAction;
use App\Api\CollectionsUpdateAction;
use App\Api\CommentsCreateAction;
use App\Api\CommentsDeleteAction;
use App\Api\CommentsListAction;
use App\Api\CommentsResolveAction;
use App\Api\CommentsUpdateAction;
use App\Api\DocumentsCreateAction;
use App\Api\DocumentsDeleteAction;
use App\Api\DocumentsInfoAction;
use App\Api\DocumentsListAction;
use App\Api\DocumentsMoveAction;
use App\Api\DocumentsRestoreAction;
use App\Api\DocumentsSearchAction;
use App\Api\DocumentsUpdateAction;
use App\Api\GroupsAddUserAction;
use App\Api\GroupsCreateAction;
use App\Api\GroupsDeleteAction;
use App\Api\GroupsInfoAction;
use App\Api\GroupsListAction;
use App\Api\GroupsRemoveUserAction;
use App\Api\GroupsUpdateAction;
use App\Api\GroupsUsersListAction;
use App\Api\HealthAction;
use App\Api\InstallationCreateAction;
use App\Api\InstallationInfoAction;
use App\Api\NotificationsDeleteAction;
use App\Api\NotificationsListAction;
use App\Api\NotificationsUpdateAction;
use App\Api\PinsCreateAction;
use App\Api\PinsDeleteAction;
use App\Api\PinsListAction;
use App\Api\ReactionsCreateAction;
use App\Api\ReactionsDeleteAction;
use App\Api\RevisionsListAction;
use App\Api\SharesCreateAction;
use App\Api\SharesInfoAction;
use App\Api\SharesListAction;
use App\Api\SharesRevokeAction;
use App\Api\SharesUpdateAction;
use App\Api\StarsCreateAction;
use App\Api\StarsDeleteAction;
use App\Api\StarsListAction;
use App\Api\TemplatesCreateAction;
use App\Api\TemplatesDeleteAction;
use App\Api\TemplatesInfoAction;
use App\Api\TemplatesListAction;
use App\Api\TemplatesUpdateAction;
use App\Api\UsersCreateAction;
use App\Api\UsersDeleteAction;
use App\Api\UsersInfoAction;
use App\Api\UsersListAction;
use App\Api\UsersSuspendAction;
use App\Api\UsersUpdateAction;
use Yiisoft\Router\Route;

return [
    Route::get('/')->action(HealthAction::class)->name('app/index'),
    Route::get('/health')->action(HealthAction::class)->name('app/health'),

    Route::post('/api/auth.config')->action(AuthConfigAction::class)->name('api/auth.config'),
    Route::post('/api/auth.info')->action(AuthInfoAction::class)->name('api/auth.info'),
    Route::post('/api/auth.delete')->action(AuthDeleteAction::class)->name('api/auth.delete'),

    Route::post('/api/installation.create')->action(InstallationCreateAction::class)->name('api/installation.create'),
    Route::post('/api/installation.info')->action(InstallationInfoAction::class)->name('api/installation.info'),

    Route::post('/api/users.list')->action(UsersListAction::class)->name('api/users.list'),
    Route::post('/api/users.info')->action(UsersInfoAction::class)->name('api/users.info'),
    Route::post('/api/users.create')->action(UsersCreateAction::class)->name('api/users.create'),
    Route::post('/api/users.update')->action(UsersUpdateAction::class)->name('api/users.update'),
    Route::post('/api/users.suspend')->action(UsersSuspendAction::class)->name('api/users.suspend'),
    Route::post('/api/users.delete')->action(UsersDeleteAction::class)->name('api/users.delete'),

    Route::post('/api/groups.list')->action(GroupsListAction::class)->name('api/groups.list'),
    Route::post('/api/groups.info')->action(GroupsInfoAction::class)->name('api/groups.info'),
    Route::post('/api/groups.create')->action(GroupsCreateAction::class)->name('api/groups.create'),
    Route::post('/api/groups.update')->action(GroupsUpdateAction::class)->name('api/groups.update'),
    Route::post('/api/groups.delete')->action(GroupsDeleteAction::class)->name('api/groups.delete'),
    Route::post('/api/groups.users')->action(GroupsUsersListAction::class)->name('api/groups.users'),
    Route::post('/api/groups.add_user')->action(GroupsAddUserAction::class)->name('api/groups.add_user'),
    Route::post('/api/groups.remove_user')->action(GroupsRemoveUserAction::class)->name('api/groups.remove_user'),

    Route::post('/api/collections.list')->action(CollectionsListAction::class)->name('api/collections.list'),
    Route::post('/api/collections.info')->action(CollectionsInfoAction::class)->name('api/collections.info'),
    Route::post('/api/collections.create')->action(CollectionsCreateAction::class)->name('api/collections.create'),
    Route::post('/api/collections.update')->action(CollectionsUpdateAction::class)->name('api/collections.update'),
    Route::post('/api/collections.delete')->action(CollectionsDeleteAction::class)->name('api/collections.delete'),

    Route::post('/api/documents.list')->action(DocumentsListAction::class)->name('api/documents.list'),
    Route::post('/api/documents.info')->action(DocumentsInfoAction::class)->name('api/documents.info'),
    Route::post('/api/documents.create')->action(DocumentsCreateAction::class)->name('api/documents.create'),
    Route::post('/api/documents.update')->action(DocumentsUpdateAction::class)->name('api/documents.update'),
    Route::post('/api/documents.move')->action(DocumentsMoveAction::class)->name('api/documents.move'),
    Route::post('/api/documents.delete')->action(DocumentsDeleteAction::class)->name('api/documents.delete'),
    Route::post('/api/documents.restore')->action(DocumentsRestoreAction::class)->name('api/documents.restore'),
    Route::post('/api/documents.search')->action(DocumentsSearchAction::class)->name('api/documents.search'),

    Route::post('/api/revisions.list')->action(RevisionsListAction::class)->name('api/revisions.list'),

    Route::post('/api/comments.list')->action(CommentsListAction::class)->name('api/comments.list'),
    Route::post('/api/comments.create')->action(CommentsCreateAction::class)->name('api/comments.create'),
    Route::post('/api/comments.update')->action(CommentsUpdateAction::class)->name('api/comments.update'),
    Route::post('/api/comments.resolve')->action(CommentsResolveAction::class)->name('api/comments.resolve'),
    Route::post('/api/comments.delete')->action(CommentsDeleteAction::class)->name('api/comments.delete'),
    Route::post('/api/reactions.create')->action(ReactionsCreateAction::class)->name('api/reactions.create'),
    Route::post('/api/reactions.delete')->action(ReactionsDeleteAction::class)->name('api/reactions.delete'),

    Route::post('/api/stars.list')->action(StarsListAction::class)->name('api/stars.list'),
    Route::post('/api/stars.create')->action(StarsCreateAction::class)->name('api/stars.create'),
    Route::post('/api/stars.delete')->action(StarsDeleteAction::class)->name('api/stars.delete'),

    Route::post('/api/pins.list')->action(PinsListAction::class)->name('api/pins.list'),
    Route::post('/api/pins.create')->action(PinsCreateAction::class)->name('api/pins.create'),
    Route::post('/api/pins.delete')->action(PinsDeleteAction::class)->name('api/pins.delete'),

    Route::post('/api/templates.list')->action(TemplatesListAction::class)->name('api/templates.list'),
    Route::post('/api/templates.info')->action(TemplatesInfoAction::class)->name('api/templates.info'),
    Route::post('/api/templates.create')->action(TemplatesCreateAction::class)->name('api/templates.create'),
    Route::post('/api/templates.update')->action(TemplatesUpdateAction::class)->name('api/templates.update'),
    Route::post('/api/templates.delete')->action(TemplatesDeleteAction::class)->name('api/templates.delete'),

    Route::post('/api/notifications.list')->action(NotificationsListAction::class)->name('api/notifications.list'),
    Route::post('/api/notifications.update')->action(NotificationsUpdateAction::class)->name('api/notifications.update'),
    Route::post('/api/notifications.delete')->action(NotificationsDeleteAction::class)->name('api/notifications.delete'),

    Route::post('/api/shares.list')->action(SharesListAction::class)->name('api/shares.list'),
    Route::post('/api/shares.info')->action(SharesInfoAction::class)->name('api/shares.info'),
    Route::post('/api/shares.create')->action(SharesCreateAction::class)->name('api/shares.create'),
    Route::post('/api/shares.update')->action(SharesUpdateAction::class)->name('api/shares.update'),
    Route::post('/api/shares.revoke')->action(SharesRevokeAction::class)->name('api/shares.revoke'),
];
