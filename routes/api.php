<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverDebtController;
use App\Http\Controllers\Api\DriverLicenseController;
use App\Http\Controllers\Api\DriverWalletController;
use App\Http\Controllers\Api\EarningController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| ORDERS
|--------------------------------------------------------------------------
*/
Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/completed', [OrderController::class, 'completedOrders']);
    Route::get('/dashboard', [OrderController::class, 'dashboard']);
    Route::get('/recent', [EarningController::class, 'recentOrders']);
    Route::post('/{order}/accept', [OrderController::class, 'accept'])->middleware('check.debt');
    Route::post('/{order}/complete', [OrderController::class, 'complete']);
    Route::post('/create', [OrderController::class, 'createOrder']);
});

/*
|--------------------------------------------------------------------------
| DRIVER
|--------------------------------------------------------------------------
*/
Route::prefix('driver')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [DriverController::class, 'profile']);
    Route::post('/profile/update', [DriverController::class, 'updateProfile']);
    Route::post('/change-password', [DriverController::class, 'changePassword']);
    Route::post('/delete-account/request', [DriverController::class, 'requestDeleteAccount']);
    Route::post('/delete-account/cancel', [DriverController::class, 'cancelDeleteAccount']);
    Route::post('/upload-license', [DriverLicenseController::class, 'upload']);
    Route::post('/update-fcm-token', [DriverController::class, 'updateFcmToken']);
    Route::post('/toggle-status', [DriverController::class, 'toggleOnline']);
    Route::post('/toggle-night-shift', [DriverController::class, 'toggleNightShift']);
    Route::get('/stats', [DriverController::class, 'stats']);
    Route::post('/update-location', [DriverController::class, 'updateLocation']);
    Route::get('/locations', [DriverController::class, 'locations']);
    Route::post('/incidents', [IncidentController::class, 'store']);
    Route::get('/notifications', [DriverController::class, 'notifications']);
    Route::post('/notifications/mark-read/{id}', [DriverController::class, 'markNotificationAsRead']);
    Route::get('/kpi', [EarningController::class, 'kpi']);
});

/*
|--------------------------------------------------------------------------
| EARNINGS
|--------------------------------------------------------------------------
*/
Route::prefix('earnings')->middleware('auth:sanctum')->group(function () {
    Route::get('/weekly', [EarningController::class, 'weekly']);
    Route::get('/monthly', [EarningController::class, 'monthly']);
});

/*
|--------------------------------------------------------------------------
| CÔNG NỢ
|--------------------------------------------------------------------------
*/
Route::prefix('debts')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [DriverDebtController::class, 'index']);
        Route::get('/{id}', [DriverDebtController::class, 'show']);
        Route::post('/{id}/pay/init', [DriverDebtController::class, 'payInit']);
        Route::post('/{id}/pay', [DriverDebtController::class, 'pay']);
        Route::post('/{id}/pay/wallet', [DriverDebtController::class, 'payWithWallet']);
    });

    // Webhook phải là public route, không đặt trong middleware auth
    Route::post('/webhook', [DriverDebtController::class, 'webhook'])
        ->withoutMiddleware([
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\CheckUserStatus::class,
        ]);
});

/*
|--------------------------------------------------------------------------
| VÍ TÀI XẾ
|--------------------------------------------------------------------------
*/
Route::prefix('wallet')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DriverWalletController::class, 'index']);
    Route::get('/transactions', [DriverWalletController::class, 'transactions']);
    Route::post('/withdraw', [DriverWalletController::class, 'withdraw']);
    Route::get('/withdraw/requests', [DriverWalletController::class, 'withdrawRequests']);
});

/*
|--------------------------------------------------------------------------
| NGÂN HÀNG
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bank', [BankController::class, 'show']);
    Route::post('/bank', [BankController::class, 'update']);
    Route::get('/bank-lists', [BankController::class, 'bankLists']);
});
Route::get('/banks', [BankController::class, 'index']);

/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
*/
Route::get('/cities', [CityController::class, 'index']);
Route::get('/shifts', [ShiftController::class, 'index']);
Route::get('/pages/{slug}', [PublicController::class, 'page']);
Route::get('/app-version', [PublicController::class, 'appVersion']);
Route::get('/banners', [PublicController::class, 'banners']);
Route::get('/support-configs', [PublicController::class, 'supportConfigs']);

