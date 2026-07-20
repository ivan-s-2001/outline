<?php

declare(strict_types=1);

use App\Api\AuthConfigAction;
use App\Api\AuthDeleteAction;
use App\Api\AuthInfoAction;
use App\Api\HealthAction;
use App\Api\InstallationCreateAction;
use App\Api\InstallationInfoAction;
use App\Web\AppIndexAction;
use Yiisoft\Router\Route;

return [
    Route::get('/')->action(AppIndexAction::class)->name('app/index'),
    Route::get('/health')->action(HealthAction::class)->name('app/health'),

    Route::post('/api/auth.config')->action(AuthConfigAction::class)->name('api/auth.config'),
    Route::post('/api/auth.info')->action(AuthInfoAction::class)->name('api/auth.info'),
    Route::post('/api/auth.delete')->action(AuthDeleteAction::class)->name('api/auth.delete'),

    Route::post('/api/installation.create')->action(InstallationCreateAction::class)->name('api/installation.create'),
    Route::post('/api/installation.info')->action(InstallationInfoAction::class)->name('api/installation.info'),
];
