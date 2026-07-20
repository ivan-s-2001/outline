<?php

declare(strict_types=1);

use App\Api\HealthAction;
use App\Api\InstallationInfoAction;
use Yiisoft\Router\Route;

return [
    Route::get('/')->action(HealthAction::class)->name('app/index'),
    Route::get('/health')->action(HealthAction::class)->name('app/health'),
    Route::post('/api/installation.info')->action(InstallationInfoAction::class)->name('api/installation.info'),
];
