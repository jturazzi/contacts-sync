<?php

use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SyncLogController;
use App\Http\Controllers\SyncRuleController;
use App\Http\Controllers\SyncSelectionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('directory.index')
        : Inertia::render('Auth/Login');
})->name('login');

Route::get('/auth/redirect', [MicrosoftAuthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/callback', [MicrosoftAuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [MicrosoftAuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::patch('/directory/{directoryUser}/toggle', [SyncSelectionController::class, 'toggle'])->name('directory.toggle');
    Route::patch('/directory/sync-all', [SyncSelectionController::class, 'updateSyncAll'])->name('directory.sync-all');

    Route::get('/rules', [SyncRuleController::class, 'index'])->name('rules.index');
    Route::post('/rules', [SyncRuleController::class, 'store'])->name('rules.store');
    Route::patch('/rules/{syncRule}', [SyncRuleController::class, 'update'])->name('rules.update');
    Route::delete('/rules/{syncRule}', [SyncRuleController::class, 'destroy'])->name('rules.destroy');
    Route::post('/rules/preview', [SyncRuleController::class, 'preview'])->name('rules.preview');

    Route::get('/logs', [SyncLogController::class, 'index'])->name('logs.index');

    Route::patch('/locale', [LocaleController::class, 'update'])->name('locale.update');
});
