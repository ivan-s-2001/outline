<?php

declare(strict_types=1);

use App\Api\AttachmentsCreateAction;
use App\Api\AttachmentsDeleteAction;
use App\Api\AttachmentsListAction;
use App\Api\AttachmentsRedirectAction;
use App\Api\AttachmentsUploadAction;
use App\Api\NotificationsDeleteAction;
use App\Api\NotificationsListAction;
use App\Api\NotificationsUpdateAction;
use App\Api\PinsCreateAction;
use App\Api\PinsDeleteAction;
use App\Api\PinsListAction;
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
use Yiisoft\Router\Route;

return [
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

    Route::post('/api/attachments.list')->action(AttachmentsListAction::class)->name('api/attachments.list'),
    Route::post('/api/attachments.create')->action(AttachmentsCreateAction::class)->name('api/attachments.create'),
    Route::post('/api/attachments.upload')->action(AttachmentsUploadAction::class)->name('api/attachments.upload'),
    Route::post('/api/attachments.delete')->action(AttachmentsDeleteAction::class)->name('api/attachments.delete'),
    Route::get('/api/attachments.redirect')->action(AttachmentsRedirectAction::class)->name('api/attachments.redirect.get'),
    Route::post('/api/attachments.redirect')->action(AttachmentsRedirectAction::class)->name('api/attachments.redirect.post'),
];
