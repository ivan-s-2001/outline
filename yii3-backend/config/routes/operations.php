<?php

declare(strict_types=1);

use App\Api\ApiKeysCreateAction;
use App\Api\ApiKeysDeleteAction;
use App\Api\ApiKeysListAction;
use App\Api\EventsListAction;
use App\Api\SubscriptionsCreateAction;
use App\Api\SubscriptionsDeleteAction;
use App\Api\SubscriptionsListAction;
use Yiisoft\Router\Route;

return [
    Route::post('/api/apiKeys.list')->action(ApiKeysListAction::class)->name('api/apiKeys.list'),
    Route::post('/api/apiKeys.create')->action(ApiKeysCreateAction::class)->name('api/apiKeys.create'),
    Route::post('/api/apiKeys.delete')->action(ApiKeysDeleteAction::class)->name('api/apiKeys.delete'),

    Route::post('/api/events.list')->action(EventsListAction::class)->name('api/events.list'),

    Route::post('/api/subscriptions.list')->action(SubscriptionsListAction::class)->name('api/subscriptions.list'),
    Route::post('/api/subscriptions.create')->action(SubscriptionsCreateAction::class)->name('api/subscriptions.create'),
    Route::post('/api/subscriptions.delete')->action(SubscriptionsDeleteAction::class)->name('api/subscriptions.delete'),
];
