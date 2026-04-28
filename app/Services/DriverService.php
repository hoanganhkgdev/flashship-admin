<?php

namespace App\Services;

use App\Models\DriverDebt;
use App\Models\DriverWallet;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DriverService
{
    public function getProfile(User $user): array
    {
        // Ép offline ngoài giờ ca (chỉ với gói tuần)
        if ($user->is_online && !$user->isInShift()) {
            $user->update(['is_online' => false]);
        }

        $latestLicense = $user->driverLicenses()->latest()->first();

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

        $shifts = $user->shifts()->get()->map(fn($shift) => [
            'id'         => $shift->id,
            'code'       => $shift->code,
            'name'       => $shift->name,
            'start_time' => Carbon::parse($shift->start_time)->format('H:i'),
            'end_time'   => Carbon::parse($shift->end_time)->format('H:i'),
            'in_shift'   => $shift->isNowInShift(),
        ])->toArray();

        return [
            'id'                    => $user->id,
            'name'                  => $user->name,
            'phone'                 => $user->phone,
            'bank_name'             => $user->bank_name,
            'bank_code'             => $user->bank_code,
            'bank_account'          => $user->bank_account,
            'bank_owner'            => $user->bank_owner,
            'status'                => $user->status,
            'role'                  => $user->getRoleNames()->first(),
            'city_id'               => $user->city_id,
            'city_name'             => $user->city?->name,
            'profile_photo_url'     => $user->profile_photo_path
                ? asset('storage/' . $user->profile_photo_path)
                : null,
            'shifts'                => $shifts,
            'is_online'             => (bool) $user->is_online,
            'plan_type'             => $user->plan?->type,
            'custom_commission_rate' => $user->custom_commission_rate,
            'license_status'        => $latestLicense?->status,
            'license_image_url'     => $latestLicense ? asset('storage/' . $latestLicense->image_path) : null,
            'can_edit_name'         => $canEditName,
            'name_updated_at'       => $user->name_updated_at ? Carbon::parse($user->name_updated_at)->toDateTimeString() : null,
            'next_name_edit_date'   => $nextEditDate,
        ];
    }

    public function toggleOnline(User $user): array
    {
        if (!$user->isInShift()) {
            $user->update(['is_online' => false]);

            FirebaseRTDBService::publishDriverProfile($user->fresh(['shifts', 'plan']));
            FirebaseRTDBService::deleteDriverLocation($user->id);

            return ['success' => false, 'is_online' => false, 'message' => 'Ngoài ca, bạn không thể bật Online', 'status' => 400];
        }

        $user->update(['is_online' => !$user->is_online]);

        FirebaseRTDBService::publishDriverProfile($user->fresh(['shifts', 'plan']));

        if (!$user->is_online) {
            FirebaseRTDBService::deleteDriverLocation($user->id);
        }

        return [
            'success'   => true,
            'is_online' => (bool) $user->is_online,
            'message'   => $user->is_online ? '🚀 Bạn đã Online' : '📤 Bạn đã Offline',
            'status'    => 200,
        ];
    }

    public function toggleNightShift(User $user): array
    {
        $user->update(['can_night_shift' => !$user->can_night_shift]);

        return [
            'success'         => true,
            'can_night_shift' => $user->can_night_shift,
            'message'         => $user->can_night_shift ? 'Bạn đã đăng ký chạy ca khuya' : 'Bạn đã hủy chạy ca khuya',
        ];
    }

    public function updateFcmToken(User $user, string $token): void
    {
        // Xóa token khỏi user khác để tránh 1 device nhận thông báo của nhiều tài khoản
        User::where('fcm_token', $token)
            ->where('id', '!=', $user->id)
            ->update(['fcm_token' => null]);

        $user->fcm_token = $token;
        $user->save();

        Log::info("✅ FCM Token updated for driver #{$user->id}");
    }

    public function updateProfile(User $user, array $data, ?string $avatarPath = null): array
    {
        $nameChanged = isset($data['name']) && $data['name'] !== $user->name;

        if ($nameChanged && $user->name_updated_at) {
            $lastUpdate = Carbon::parse($user->name_updated_at);
            $now = Carbon::now();
            if ($lastUpdate->year == $now->year && $lastUpdate->month == $now->month) {
                return ['success' => false, 'message' => 'Bạn chỉ được đổi tên 1 lần trong 1 tháng.', 'status' => 400];
            }
        }

        if ($nameChanged) {
            $user->name_updated_at = now();
        }

        if (isset($data['name']))  $user->name  = $data['name'];
        if (isset($data['phone'])) $user->phone = $data['phone'];

        if (isset($data['fcm_token'])) {
            $this->updateFcmToken($user, $data['fcm_token']);
        }

        if ($avatarPath) {
            $user->profile_photo_path = $avatarPath;
        }

        $user->save();

        return [
            'success' => true,
            'user'    => [
                'id'                => $user->id,
                'name'              => $user->name,
                'phone'             => $user->phone,
                'profile_photo_url' => $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : null,
            ],
            'status' => 200,
        ];
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): array
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return ['success' => false, 'message' => 'Mật khẩu cũ không đúng', 'status' => 400];
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        return ['success' => true, 'message' => 'Đổi mật khẩu thành công', 'status' => 200];
    }

    public function updateLocation(User $user, float $latitude, float $longitude): void
    {
        $user->latitude             = $latitude;
        $user->longitude            = $longitude;
        $user->last_location_update = now();
        $user->save();

        FirebaseRTDBService::publishDriverLocation($user->id, $latitude, $longitude);
    }

    public function clearStatsCache(int $userId): void
    {
        Cache::forget("driver_stats_{$userId}");
    }

    public function getStats(User $user): array
    {
        return Cache::remember("driver_stats_{$user->id}", 120, fn() => $this->computeStats($user));
    }

    private function computeStats(User $user): array
    {
        $now          = Carbon::now();
        $today        = Carbon::today()->toDateString();
        $startOfWeek  = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth   = $now->copy()->endOfMonth();

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
        $todayBonus           = (float) ($row->today_bonus ?? 0);
        $todayOrders          = (int)   ($row->today_orders ?? 0);
        $weekShipping         = (float) ($row->week_shipping ?? 0);
        $weekBonus            = (float) ($row->week_bonus ?? 0);
        $weekOrders           = (int)   ($row->week_orders ?? 0);
        $totalShipping        = (float) ($row->month_shipping ?? 0);
        $totalBonus           = (float) ($row->month_bonus ?? 0);
        $completedOrdersCount = (int)   ($row->month_orders ?? 0);

        $cancelledOrdersCount = Order::where('delivery_man_id', $user->id)
            ->where('status', 'cancelled')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->count();

        $totalAttempted = $completedOrdersCount + $cancelledOrdersCount;
        $completionRate = $totalAttempted > 0 ? round(($completedOrdersCount / $totalAttempted) * 100) : 100;

        $wallet = DriverWallet::firstOrCreate(['driver_id' => $user->id]);

        $unpaidDebts = DriverDebt::where('driver_id', $user->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->get();

        $debtWeek   = $unpaidDebts->sum(fn($d) => $d->amount_due - $d->amount_paid);
        $debtStatus = $unpaidDebts->contains('status', 'overdue') ? 'overdue' : ($unpaidDebts->isNotEmpty() ? 'pending' : 'paid');

        return [
            'today_income'    => (float) ($todayShipping + $todayBonus),
            'today_shipping'  => (float) $todayShipping,
            'today_bonus'     => (float) $todayBonus,
            'week_income'     => (float) ($weekShipping + $weekBonus),
            'week_shipping'   => (float) $weekShipping,
            'week_bonus'      => (float) $weekBonus,
            'total_income'    => (float) ($totalShipping + $totalBonus),
            'total_shipping'  => (float) $totalShipping,
            'total_bonus'     => (float) $totalBonus,
            'today_orders'    => $todayOrders,
            'week_orders'     => $weekOrders,
            'total_orders'    => $completedOrdersCount,
            'completion_rate' => $completionRate,
            'cancelled_orders' => $cancelledOrdersCount,
            'completed_orders' => $completedOrdersCount,
            'wallet_balance'  => (float) ($wallet->balance ?? 0),
            'debt_week'       => (float) $debtWeek,
            'debt_status'     => $debtStatus,
        ];
    }

    public function getNotifications(User $user, int $page = 1, int $perPage = 20): array
    {
        $paginator = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function ($n) {
            $payload = $n->data ?? [];
            return [
                'id'         => $n->id,
                'title'      => $payload['title']   ?? 'Thông báo hệ thống',
                'message'    => $payload['message'] ?? '',
                'type'       => $payload['type']    ?? 'info',
                'data'       => array_diff_key($payload, array_flip(['title', 'message', 'type'])),
                'is_read'    => $n->read_at !== null,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ];
        });

        return [
            'data'         => $items,
            'unread_count' => $user->unreadNotifications()->count(),
            'has_more'     => $paginator->hasMorePages(),
            'current_page' => $paginator->currentPage(),
            'total'        => $paginator->total(),
        ];
    }
}
