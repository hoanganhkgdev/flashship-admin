<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\DriverDebtController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\DriverWalletController;
use App\Http\Controllers\Api\DriverLicenseController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\DriverAnnouncementController;

use App\Models\Page;
use App\Models\Setting;
use App\Models\SupportConfig;
use Illuminate\Support\Facades\Route;
use App\Models\Banner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Order;
use Illuminate\Http\Request;

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
| ORDERS (protected)
|--------------------------------------------------------------------------
*/
Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/completed', [OrderController::class, 'completedOrders']);
    Route::get('/dashboard', [OrderController::class, 'dashboard']);
    Route::post('/{order}/accept', [OrderController::class, 'accept'])
        ->middleware('check.debt');
    Route::post('/{order}/complete', [OrderController::class, 'complete']);
    Route::post('/create', [OrderController::class, 'createOrder']);
});


/*
|--------------------------------------------------------------------------
| DRIVER
|--------------------------------------------------------------------------
*/
Route::prefix('driver')->group(function () {
    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        // Profile & account
        Route::post('/profile/update', [DriverController::class, 'updateProfile']);
        Route::post('/change-password', [DriverController::class, 'changePassword']);
        Route::delete('/delete-account', [DriverController::class, 'deleteAccount']);
        Route::get('/profile', [DriverController::class, 'profile']);
        Route::post('/upload-license', [DriverLicenseController::class, 'upload']);

        // ✅ FCM Token (Firebase Cloud Messaging)
        Route::post('/update-fcm-token', [DriverController::class, 'updateFcmToken']);

        // Status
        Route::post('/toggle-status', [DriverController::class, 'toggleOnline']);
        Route::post('/toggle-night-shift', [DriverController::class, 'toggleNightShift']);
        Route::get('/stats', [DriverController::class, 'stats']);

        // Location
        Route::post('/update-location', [DriverController::class, 'updateLocation']);
        Route::get('/locations', [DriverController::class, 'locations']);

        // Incident Reporting (Báo sự cố)
        Route::post('/incidents', [IncidentController::class, 'store']);

        // Notifications
        Route::get('/notifications', [DriverController::class, 'notifications']);
        Route::post('/notifications/mark-read/{id}', [DriverController::class, 'markNotificationAsRead']);
    });
});
/*
|--------------------------------------------------------------------------
| CÔNG NỢ
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('debts')->group(function () {
        Route::get('/', [DriverDebtController::class, 'index']);
        Route::get('/{id}', [DriverDebtController::class, 'show']);
        Route::post('/{id}/pay/init', [DriverDebtController::class, 'payInit']);
        Route::post('/{id}/pay', [DriverDebtController::class, 'pay']);
        Route::post('/{id}/pay/wallet', [DriverDebtController::class, 'payWithWallet']);

        // ⚠️ Webhook phải là PUBLIC route, không được đặt trong middleware auth
        // Route webhook public được định nghĩa ở dòng 116

    });
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


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::get('/cities', [CityController::class, 'index']);
Route::get('/shifts', [ShiftController::class, 'index']);
Route::prefix('debts')->group(function () {
    Route::post('/webhook', [DriverDebtController::class, 'webhook'])
        ->withoutMiddleware([
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\CheckUserStatus::class,
        ]);
});
// 🚨 Báo cáo sự cố (SOS) - Đã có route phía trên (line 85)



Route::post('/zalo/webhook', [\App\Http\Controllers\Api\ZaloWebhookController::class, 'handle']);

Route::get('/facebook/webhook', [\App\Http\Controllers\Api\FacebookWebhookController::class, 'verify']);
Route::post('/facebook/webhook', [\App\Http\Controllers\Api\FacebookWebhookController::class, 'handle']);
Route::get('/banks', [BankController::class, 'index']);


Route::get('/pages/{slug}', function ($slug) {
    $page = Page::where('slug', $slug)->firstOrFail();

    return response()->json([
        'title' => $page->title,
        'content' => $page->content,
    ]);
});

Route::get('/app-version', function () {
    return response()->json([
        'latest_version' => Setting::where('key', 'app_version')->value('value') ?? '1.0.0',
        'force_update' => filter_var(
            Setting::where('key', 'force_update')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'store_url' => [
            'android' => Setting::where('key', 'store_url_android')->value('value'),
            'ios' => Setting::where('key', 'store_url_ios')->value('value'),
        ],
    ]);
});

Route::get('/banners', function () {
    return Banner::where('is_active', true)
        ->orderBy('id', 'desc')
        ->get()
        ->map(fn($b) => [
            'id' => $b->id,
            'title' => $b->title,
            'image_url' => asset('storage/' . $b->image),
        ]);
});

Route::get('/support-configs', function (Request $request) {
    $cityId = $request->query('city_id');
    
    // Nếu có token (driver đã đăng nhập), lấy city_id của driver làm ưu tiên
    $user = auth('sanctum')->user();
    if ($user) {
        $cityId = $user->city_id;
    }

    return SupportConfig::where('is_active', true)
        ->where(function($query) use ($cityId) {
            $query->whereNull('city_id'); // Lấy các cấu hình chung (Global)
            if ($cityId) {
                $query->orWhere('city_id', $cityId); // Lấy các cấu hình riêng cho thành phố đó
            }
        })
        ->orderBy('priority', 'asc')
        ->get()
        ->map(fn($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'subtitle' => $s->subtitle,
            'icon' => $s->icon,
            'type' => $s->type,
            'value' => $s->value,
            'color' => $s->color,
            'city_id' => $s->city_id,
            'priority' => $s->priority,
            'is_active' => $s->is_active,
        ]);
});

Route::middleware('auth:sanctum')->get('/earnings/weekly', function (Request $request) {
    $driverId = $request->user()->id;
    $startOfWeek = Carbon::now()->startOfWeek();
    $endOfWeek = Carbon::now()->endOfWeek();

    $data = DB::table('orders')
        ->selectRaw('DATE(completed_at) as date, SUM(shipping_fee) as shipping, SUM(bonus_fee) as bonus')
        ->where('delivery_man_id', $driverId)
        ->where('status', 'completed')
        ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
        ->groupBy('date')
        ->get()
        ->keyBy('date');

    $result = [];
    foreach (range(0, 6) as $i) {
        $day = $startOfWeek->copy()->addDays($i)->toDateString();
        $row = $data->get($day);
        $result[] = [
            'date' => $day,
            'total' => (float) (($row->shipping ?? 0) + ($row->bonus ?? 0)),
            'shipping' => (float) ($row->shipping ?? 0),
            'bonus' => (float) ($row->bonus ?? 0),
        ];
    }

    return $result;
});

Route::middleware('auth:sanctum')->get('/earnings/monthly', function (Request $request) {
    $driverId = $request->user()->id;

    $startOfMonth = Carbon::now()->startOfMonth();
    $endOfMonth = Carbon::now()->endOfMonth();

    $data = DB::table('orders')
        ->selectRaw('DATE(completed_at) as date, SUM(shipping_fee) as shipping, SUM(bonus_fee) as bonus')
        ->where('delivery_man_id', $driverId)
        ->where('status', 'completed')
        ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
        ->groupBy('date')
        ->get()
        ->keyBy('date');

    $result = [];
    $daysInMonth = $startOfMonth->daysInMonth;

    for ($i = 0; $i < $daysInMonth; $i++) {
        $day = $startOfMonth->copy()->addDays($i)->toDateString();
        $row = $data->get($day);
        $result[] = [
            'date' => $day,
            'total' => (float) (($row->shipping ?? 0) + ($row->bonus ?? 0)),
            'shipping' => (float) ($row->shipping ?? 0),
            'bonus' => (float) ($row->bonus ?? 0),
        ];
    }

    return $result;
});


Route::middleware('auth:sanctum')->get('/orders/recent', function (Request $request) {
    $driverId = $request->user()->id;

    $orders = Order::where('delivery_man_id', $driverId)
        ->orderByDesc('id')
        ->take(5) // lấy 5 đơn gần nhất
        ->get(['id', 'status', 'shipping_fee', 'bonus_fee', 'created_at']);

    return $orders->map(fn($o) => [
        'id' => $o->id,
        'status' => $o->status,
        'shipping_fee' => $o->shipping_fee,
        'bonus_fee' => $o->bonus_fee,
        'created_at' => $o->created_at->toDateTimeString(),
    ]);
});

Route::middleware('auth:sanctum')->get('/driver/kpi', function (Request $request) {
    $driver = $request->user();

    $startOfWeek = Carbon::now()->startOfWeek();
    $endOfWeek = Carbon::now()->endOfWeek();

    $ordersDone = Order::where('delivery_man_id', $driver->id)
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->count();

    $earningsShipping = Order::where('delivery_man_id', $driver->id)
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->where('status', 'completed') // ✅ Chỉ tính đơn hoàn thành
        ->sum('shipping_fee');

    $earningsBonus = Order::where('delivery_man_id', $driver->id)
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->where('status', 'completed')
        ->sum('bonus_fee');

    return response()->json([
        "success" => true,
        "message" => "KPI tuần của tài xế",
        "data" => [
            "orders_done" => $ordersDone,
            "orders_target" => 20,
            "earnings_done" => $earningsShipping + $earningsBonus,
            "earnings_shipping" => $earningsShipping,
            "earnings_bonus" => $earningsBonus,
            "earnings_target" => 2000000,
        ],
    ]);
});


// Ví tài xế
Route::middleware('auth:sanctum')->prefix('wallet')->group(function () {
    Route::get('/', [DriverWalletController::class, 'index']); // xem số dư
    Route::get('/transactions', [DriverWalletController::class, 'transactions']); // lịch sử
    Route::post('/withdraw', [DriverWalletController::class, 'withdraw']); // rút tiền
    Route::get('/withdraw/requests', [DriverWalletController::class, 'withdrawRequests']);

});

Route::middleware('auth:sanctum')->get('/driver/announcement', DriverAnnouncementController::class);



