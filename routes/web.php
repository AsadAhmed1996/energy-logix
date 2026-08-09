<?php

use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::redirect('/dashboard', '/');

    // Customer CRUD
    Route::get('/address-lookup', [CustomerController::class, 'addressLookup'])->name('address.lookup');
    Route::resource('customers', CustomerController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    // Sync APIs
    Route::post('/sync/trigger', [SyncController::class, 'sync'])->middleware('throttle:5,1')->name('sync.trigger');
    Route::get('/sync/status/{log}', [SyncController::class, 'status'])->name('sync.status');
    Route::get('/sync/latest-status', [SyncController::class, 'latestStatus'])->name('sync.latest-status');
    Route::get('/sync/logs', [SyncController::class, 'logs'])->name('sync.logs');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::get('/captcha/replenish', [CaptchaController::class, 'replenish'])->name('captcha.replenish');

require __DIR__.'/auth.php';
