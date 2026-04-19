<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminApi\AuthController;
// use App\Http\Controllers\AdminApi\DashboardController;
use App\Http\Controllers\AdminApi\OrderController;
// use App\Http\Controllers\AdminApi\UserController;
use App\Http\Controllers\AdminApi\FinanceController;
use App\Http\Controllers\AdminApi\CityController;
use App\Http\Controllers\AdminApi\DriverController;
use App\Http\Controllers\AdminApi\ReportController;

Route::prefix('admin')->group(function () {

    // Đăng nhập (public)
    Route::post('login', [AuthController::class, 'login']);

    // Các route cần token
    Route::middleware(['auth:sanctum', 'city.scope'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // Dashboard KPI
        // Route::get('dashboard', [DashboardController::class, 'index']);

        // Orders
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::post('orders/{id}/assign', [OrderController::class, 'assign']);
        Route::get('drivers/{id}/active-orders', [OrderController::class, 'activeOrdersByDriver']);

        // Users
        Route::get('drivers', [DriverController::class, 'index']);
        // Route::get('dispatchers', [UserController::class, 'dispatchers']);

        // Finance
        Route::get('wallets', [FinanceController::class, 'wallets']);
        Route::get('debts', [FinanceController::class, 'debts']);
        Route::get('payouts', [FinanceController::class, 'payouts']);

        // City
        Route::get('cities', [CityController::class, 'index']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('revenue', [ReportController::class, 'revenue']);
        Route::get('orders', [ReportController::class, 'orders']);
        Route::get('drivers', [ReportController::class, 'drivers']);
    });
});