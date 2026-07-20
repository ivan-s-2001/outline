<?php

declare(strict_types=1);

use App\Api\GroupsAddUserAction;
use App\Api\GroupsCreateAction;
use App\Api\GroupsDeleteAction;
use App\Api\GroupsInfoAction;
use App\Api\GroupsListAction;
use App\Api\GroupsRemoveUserAction;
use App\Api\GroupsUpdateAction;
use App\Api\GroupsUsersListAction;
use App\Api\UsersCreateAction;
use App\Api\UsersDeleteAction;
use App\Api\UsersInfoAction;
use App\Api\UsersListAction;
use App\Api\UsersSuspendAction;
use App\Api\UsersUpdateAction;
use Yiisoft\Router\Route;

return [
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
];
