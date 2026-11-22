<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisionController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/login', [AuthController::class, 'show'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('/checkpoint', 'checkpoint.index')->middleware('role:supervisor,admin')->name('checkpoint.index');
    Route::middleware('role:staff')->group(function () {
        Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::post('/transactions/{transaction}/scan', [ScanController::class, 'store'])->name('transactions.scan');
        Route::post('/transactions/{transaction}/demo-checkpoint', [VisionController::class, 'demo'])->name('transactions.demo');
    });
    // Checkpoint results are submitted by the supervised camera/inference flow.
    Route::post('/transactions/{transaction}/capture', [VisionController::class, 'store'])->middleware('role:supervisor,admin')->name('transactions.capture');
    Route::resource('transactions', TransactionController::class)->only(['index', 'show']);
    Route::get('/evidence/{visionEvent}', [VisionController::class, 'evidence'])->name('evidence.show');
    Route::get('/reviews', [ReviewController::class, 'index'])->middleware('role:supervisor,admin')->name('reviews.index');
    Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->middleware('role:supervisor,admin')->name('reviews.update');
    Route::middleware('role:admin')->group(function () {
        Route::resource('products', ProductController::class)->only(['index', 'store', 'update']);
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::post('/warehouses/{warehouse}/cameras', [WarehouseController::class, 'camera'])->name('warehouses.cameras.store');
        Route::patch('/cameras/{camera}', [WarehouseController::class, 'updateCamera'])->name('cameras.update');
        Route::get('/audits', [AuditController::class, 'index'])->name('audits.index');
    });
});
