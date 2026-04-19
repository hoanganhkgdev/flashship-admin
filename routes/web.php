<?php

use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/payment/success', function (\Illuminate\Http\Request $request) {
    $query = $request->getQueryString();
    $deepLink = 'flashshipdriver://payos/success' . ($query ? '?' . $query : '');
    return response()->view('payment_redirect', ['deepLink' => $deepLink, 'status' => 'success']);
});

Route::get('/payment/cancel', function (\Illuminate\Http\Request $request) {
    $query = $request->getQueryString();
    $deepLink = 'flashshipdriver://payos/cancel' . ($query ? '?' . $query : '');
    return response()->view('payment_redirect', ['deepLink' => $deepLink, 'status' => 'cancel']);
});

Route::get('/pages/{slug}', function ($slug) {
    $page = Page::where('slug', $slug)->firstOrFail();
    return view('page', compact('page'));
});

Route::middleware(['auth'])->group(function () {
    Route::post('/admin/update-fcm-token', function (\Illuminate\Http\Request $request) {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['success' => true]);
    })->name('admin.update-fcm-token');

    // ✅ AJAX routes cho bản đồ tài xế
    Route::prefix('ajax')->group(function () {
        Route::get('drivers/map-data', [\App\Http\Controllers\AdminApi\DriverController::class, 'mapData']);
        Route::get('drivers/{id}/active-orders', [\App\Http\Controllers\AdminApi\OrderController::class, 'activeOrdersByDriver']);
    });
});
