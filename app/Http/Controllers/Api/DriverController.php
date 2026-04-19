<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\DriverDebt;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Models\DriverWallet;
use Illuminate\Support\Facades\DB;
use App\Services\FirebaseRTDBService;


class DriverController extends Controller
{
    // Thông tin tài khoản
    public function profile(Request $request)
    {
        $user = $request->user();

        // 🚫 Ép offline ngoài giờ ca (chỉ với gói tuần, không áp dụng gói %)
        if ($user->is_online && !$user->isInShift()) {
            $user->update(['is_online' => false]);
        }

        $latestLicense = $user->driverLicenses()->latest()->first();

        // 🔹 Kiểm tra xem có thể chỉnh sửa tên không (1 lần/tháng)
        $canEditName = true;
        $nextEditDate = null;
        if ($user->name_updated_at) {
            $lastUpdate = Carbon::parse($user->name_updated_at);
            $now = Carbon::now();

            if ($lastUpdate->year == $now->year && $lastUpdate->month == $now->month) {
                $canEditName = false;
                $nextEditDate = $lastUpdate->copy()->addMonth()->startOfMonth()->toDateString();
            }
        }

        $shifts = $user->shifts()->get()->map(function ($shift) {
            return [
                'id'         => $shift->id,
                'code'       => $shift->code,
                'name'       => $shift->name,
                'start_time' => Carbon::parse($shift->start_time)->format('H:i'),
                'end_time'   => Carbon::parse($shift->end_time)->format('H:i'),
                'in_shift'   => $shift->isNowInShift(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Thông tin tài khoản',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'bank_name' => $user->bank_name,
                    'bank_code' => $user->bank_code,
                    'bank_account' => $user->bank_account,
                    'bank_owner' => $user->bank_owner,
                    'status' => $user->status,
                    'role' => $user->getRoleNames()->first(),
                    'city_id' => $user->city_id,
                    'city_name' => $user->city?->name,
                    'profile_photo_url' => $user->profile_photo_path
                        ? asset('storage/' . $user->profile_photo_path)
                        : null,
                    'shifts' => $shifts,
                    'is_online' => (bool) $user->is_online,
                    'plan_type' => $user->plan?->type, // ✅ 'commission' hoặc 'weekly'
                    'custom_commission_rate' => $user->custom_commission_rate, // ✅ % chiết khấu riêng (nếu có)
                    'license_status' => $latestLicense?->status,
                    'license_image_url' => $latestLicense ? asset('storage/' . $latestLicense->image_path) : null,
                    'can_edit_name' => $canEditName,
                    'name_updated_at' => $user->name_updated_at ? Carbon::parse($user->name_updated_at)->toDateTimeString() : null,
                    'next_name_edit_date' => $nextEditDate,
                ]
            ],
        ]);
    }

    // Đăng ký ca khuya
    public function toggleNightShift(Request $request)
    {
        $user = $request->user();
        $user->update([
            'can_night_shift' => !$user->can_night_shift,
        ]);

        return response()->json([
            'success' => true,
            'can_night_shift' => $user->can_night_shift,
            'message' => $user->can_night_shift ? 'Bạn đã đăng ký chạy ca khuya' : 'Bạn đã hủy chạy ca khuya',
        ]);
    }

    // Nút tắt/mở app
    public function toggleOnline(Request $request)
    {
        $user = $request->user();

        if (!$user->isInShift()) {
            $user->update(['is_online' => false]);
            
            // 🚀 Sync ngay lập tức lên Firebase để App chuyển sang Offline
            FirebaseRTDBService::publishDriverProfile($user->fresh(['shifts', 'plan']));
            FirebaseRTDBService::deleteDriverLocation($user->id);

            return response()->json([
                'success'   => false,
                'is_online' => false,
                'message'   => 'Ngoài ca, bạn không thể bật Online',
            ], 400);
        }

        $user->update(['is_online' => !$user->is_online]);

        // 🚀 Sync trạng thái mới lên Firebase để app đọc real-time
        FirebaseRTDBService::publishDriverProfile($user->fresh(['shifts', 'plan']));

        // Nếu Offline -> Xóa marker trên bản đồ của Admin ngay lập tức
        if (!$user->is_online) {
            FirebaseRTDBService::deleteDriverLocation($user->id);
        }

        return response()->json([
            'success'   => true,
            'is_online' => (bool) $user->is_online,
            'message'   => $user->is_online ? '🚀 Bạn đã Online' : '📤 Bạn đã Offline',
        ]);
    }

    // Cập nhật thông tin profile
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // ✅ Nếu chỉ gửi fcm_token (từ Flutter), xử lý nhanh và return
        if ($request->has('fcm_token') && !$request->has('name') && !$request->has('phone')) {
            $newToken = $request->fcm_token;
            \App\Models\User::where('fcm_token', $newToken)
                ->where('id', '!=', $user->id)
                ->update(['fcm_token' => null]);
            $user->fcm_token = $newToken;
            $user->save();
            \Log::info("✅ Updated fcm_token for driver #{$user->id}");
            return response()->json(['success' => true, 'message' => 'Cập nhật fcm_token thành công']);
        }

        $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'required|string|max:20',
            'avatar'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'fcm_token' => 'nullable|string|max:500',
        ]);

        $nameChanged = $request->name !== $user->name;
        if ($nameChanged) {
            if ($user->name_updated_at) {
                $lastUpdate = Carbon::parse($user->name_updated_at);
                $now = Carbon::now();
                if ($lastUpdate->year == $now->year && $lastUpdate->month == $now->month) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ được đổi tên 1 lần trong 1 tháng.',
                    ], 400);
                }
            }
            $user->name_updated_at = now();
        }

        $user->name = $request->name;
        $user->phone = $request->phone;

        // ✅ Lưu fcm_token nếu có gửi kèm
        if ($request->filled('fcm_token')) {
            $newToken = $request->fcm_token;
            \App\Models\User::where('fcm_token', $newToken)
                ->where('id', '!=', $user->id)
                ->update(['fcm_token' => null]);
            $user->fcm_token = $newToken;
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('profile-photos', 'public');
            $user->profile_photo_path = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'profile_photo_url' => $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : null,
            ]
        ]);
    }

    /**
     * ✅ Cập nhật FCM Token (endpoint riêng, dùng từ Flutter notification_service)
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $newToken = $request->fcm_token;

        // Xóa token này khỏi các user khác để tránh 1 device nhận thông báo của nhiều tài khoản
        \App\Models\User::where('fcm_token', $newToken)
            ->where('id', '!=', $user->id)
            ->update(['fcm_token' => null]);

        $user->fcm_token = $newToken;
        $user->save();

        \Log::info("✅ FCM Token updated for driver #{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'FCM Token đã được cập nhật thành công',
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Mật khẩu cũ không đúng'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();
        return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công']);
    }

    public function deleteAccount(Request $request)
    {
        $request->user()->delete();
        return response()->json(['success' => true, 'message' => 'Tài khoản đã được xóa']);
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = $request->user();
        $user->latitude = $request->latitude;
        $user->longitude = $request->longitude;
        $user->last_location_update = now();
        $user->save();

        // 🚀 Sync REALTIME lên Firebase để Admin thấy xe chạy trực tiếp
        FirebaseRTDBService::publishDriverLocation($user->id, $user->latitude, $user->longitude);

        return response()->json(['success' => true, 'message' => 'Cập nhật vị trí thành công']);
    }

    public function locations()
    {
        $drivers = User::role('driver')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'latitude', 'longitude', 'last_location_update')
            ->get();

        return response()->json(['success' => true, 'data' => $drivers]);
    }

    public function updateBank(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'bank_code' => 'required|string|max:50',
            'bank_name' => 'required|string|max:255',
            'bank_account' => 'required|string|max:50',
            'bank_owner' => 'required|string|max:255',
        ]);

        $user->update($request->only('bank_code', 'bank_name', 'bank_account', 'bank_owner'));
        return response()->json(['success' => true, 'message' => 'Cập nhật ngân hàng thành công', 'user' => $user]);
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();
        $today = Carbon::today()->toDateString();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // 🔹 Gộp tất cả tính toán thu nhập + đơn vào 1 query (thay vì 10 query riêng)
        $row = Order::where('delivery_man_id', $user->id)
            ->where('status', 'completed')
            ->selectRaw("
                SUM(CASE WHEN DATE(completed_at) = ? THEN shipping_fee ELSE 0 END) as today_shipping,
                SUM(CASE WHEN DATE(completed_at) = ? THEN bonus_fee    ELSE 0 END) as today_bonus,
                COUNT(CASE WHEN DATE(completed_at) = ? THEN 1 END)                 as today_orders,

                SUM(CASE WHEN completed_at BETWEEN ? AND ? THEN shipping_fee ELSE 0 END) as week_shipping,
                SUM(CASE WHEN completed_at BETWEEN ? AND ? THEN bonus_fee    ELSE 0 END) as week_bonus,
                COUNT(CASE WHEN completed_at BETWEEN ? AND ? THEN 1 END)                 as week_orders,

                SUM(CASE WHEN completed_at BETWEEN ? AND ? THEN shipping_fee ELSE 0 END) as month_shipping,
                SUM(CASE WHEN completed_at BETWEEN ? AND ? THEN bonus_fee    ELSE 0 END) as month_bonus,
                COUNT(CASE WHEN completed_at BETWEEN ? AND ? THEN 1 END)                 as month_orders
            ", [
                $today, $today, $today,
                $startOfWeek, $now, $startOfWeek, $now, $startOfWeek, $now,
                $startOfMonth, $endOfMonth, $startOfMonth, $endOfMonth, $startOfMonth, $endOfMonth,
            ])->first();

        $todayShipping        = (float) ($row->today_shipping ?? 0);
        $todayBonus           = (float) ($row->today_bonus    ?? 0);
        $todayOrders          = (int)   ($row->today_orders   ?? 0);
        $weekShipping         = (float) ($row->week_shipping  ?? 0);
        $weekBonus            = (float) ($row->week_bonus     ?? 0);
        $weekOrders           = (int)   ($row->week_orders    ?? 0);
        $totalShipping        = (float) ($row->month_shipping ?? 0);
        $totalBonus           = (float) ($row->month_bonus    ?? 0);
        $completedOrdersCount = (int)   ($row->month_orders   ?? 0);

        // 🔹 TỶ LỆ HOÀN THÀNH (cancelled cần query riêng vì khác status)
        $cancelledOrdersCount = Order::where('delivery_man_id', $user->id)
            ->where('status', 'cancelled')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->count();

        $totalAttempted = $completedOrdersCount + $cancelledOrdersCount;
        $completionRate = $totalAttempted > 0 ? round(($completedOrdersCount / $totalAttempted) * 100) : 100;

        // 🔹 VÍ & CÔNG NỢ
        $wallet = DriverWallet::firstOrCreate(['driver_id' => $user->id]);

        $unpaidDebts = \App\Models\DriverDebt::where('driver_id', $user->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->get();

        $debtWeek = $unpaidDebts->sum(fn($d) => $d->amount_due - $d->amount_paid);
        $debtStatus = $unpaidDebts->contains('status', 'overdue') ? 'overdue' : ($unpaidDebts->isNotEmpty() ? 'pending' : 'paid');

        return response()->json([
            'success' => true,
            'data' => [
                'today_income' => (float) ($todayShipping + $todayBonus),
                'today_shipping' => (float) $todayShipping,
                'today_bonus' => (float) $todayBonus,

                'week_income' => (float) ($weekShipping + $weekBonus),
                'week_shipping' => (float) $weekShipping,
                'week_bonus' => (float) $weekBonus,

                'total_income' => (float) ($totalShipping + $totalBonus),
                'total_shipping' => (float) $totalShipping,
                'total_bonus' => (float) $totalBonus,

                'today_orders' => $todayOrders,
                'week_orders' => $weekOrders,
                'total_orders' => $completedOrdersCount,
                'completion_rate' => $completionRate,
                'cancelled_orders' => $cancelledOrdersCount,
                'completed_orders' => $completedOrdersCount,
                'wallet_balance' => (float) ($wallet->balance ?? 0),
                'debt_week' => (float) $debtWeek,
                'debt_status' => $debtStatus,
            ]
        ]);
    }

    /**
     * Lấy danh sách thông báo của tài xế
     */
    public function notifications(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $notifications = \DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', \App\Models\User::class)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(function($n) {
                $data = $n->data ? json_decode($n->data, true) : [];
                return [
                    'id'         => $n->id,
                    'title'      => $data['title'] ?? 'Thông báo hệ thống',
                    'message'    => $data['message'] ?? $data['body'] ?? '',
                    'type'       => $data['type'] ?? 'info',
                    'read_at'    => $n->read_at,
                    'created_at' => $n->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $notifications,
            'unread_count' => $user->unreadNotifications()->count()
        ]);
    }

    /**
     * Đánh dấu thông báo đã đọc
     */
    public function markNotificationAsRead(\Illuminate\Http\Request $request, $id)
    {
        $user = $request->user();
        if ($id === 'all') {
            $user->unreadNotifications()->update(['read_at' => now()]);
        } else {
            $notification = $user->notifications()->where('id', $id)->first();
            if ($notification) $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

}
