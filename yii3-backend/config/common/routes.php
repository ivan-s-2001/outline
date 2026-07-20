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
use App\Api\DocumentsCreateAction;
use App\Api\DocumentsDeleteAction;
use App\Api\DocumentsInfoAction;
use App\Api\DocumentsListAction;
use App\Api\DocumentsMoveAction;
use App\Api\DocumentsRestoreAction;
use App\Api\DocumentsSearchAction;
use App\Api\DocumentsUpdateAction;
use App\Api\HealthAction;
use App\Api\InstallationCreateAction;
use App\Api\InstallationInfoAction;
use App\Api\RevisionsListAction;
use Yiisoft\Router\Route;

return [
    Route::get('/')->action(HealthAction::class)->name('app/index'),
    Route::get('/health')->action(HealthAction::class)->name('app/health'),

    Route::post('/api/auth.config')->action(AuthConfigAction::class)->name('api/auth.config'),
    Route::post('/api/auth.info')->action(AuthInfoAction::class)->name('api/auth.info'),
    Route::post('/api/auth.delete')->action(AuthDeleteAction::class)->name('api/auth.delete'),

    Route::post('/api/installation.create')->action(InstallationCreateAction::class)->name('api/installation.create'),
    Route::post('/api/installation.info')->action(InstallationInfoAction::class)->name('api/installation.info'),

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
];
