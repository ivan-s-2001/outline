<?php

declare(strict_types=1);

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
use App\Api\ReactionsCreateAction;
use App\Api\ReactionsDeleteAction;
use App\Api\RevisionsListAction;
use Yiisoft\Router\Route;

return [
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
];
