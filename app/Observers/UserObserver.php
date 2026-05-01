<?php

namespace App\Observers;

use App\Helpers\FcmHelper;
use App\Models\User;
use App\Services\FirebaseRTDBService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    // Các field khi thay đổi cần sync lại Firebase profile
    private const FIREBASE_FIELDS = [
        'name', 'phone', 'is_online', 'status',
        'city_id', 'plan_id', 'custom_commission_rate',
    ];

    public function updated(User $user): void
    {
        // Chỉ sync khi là driver (không sync admin/staff)
        if (!$user->hasRole('driver')) return;

        // Chỉ sync khi có ít nhất 1 field quan trọng thay đổi
        $changed = array_intersect(array_keys($user->getDirty()), self::FIREBASE_FIELDS);
        if (empty($changed)) return;

        Log::info("👤 [UserObserver] Driver #{$user->id} thay đổi: " . implode(', ', $changed) . " → sync Firebase");

        // Tài khoản bị khóa → force logout qua FCM
        if (in_array('status', $changed) && (int) $user->status === 2 && $user->fcm_token) {
            FcmHelper::sendSingle(
                $user->fcm_token,
                'Tài khoản bị khóa',
                'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ admin.',
                ['type' => 'force_logout'],
            );
            Log::info("🔒 [UserObserver] Driver #{$user->id} bị khóa → gửi FCM force_logout");
        }

        try {
            FirebaseRTDBService::publishDriverProfile($user->fresh(['shifts', 'plan']));
        } catch (\Throwable $e) {
            Log::error("❌ [UserObserver] Firebase sync lỗi driver #{$user->id}: " . $e->getMessage());
        }
    }

    public function deleting(User $user): void
    {
        // Không thể dùng hasRole() ở đây: Spatie's HasRoles trait đăng ký deleting listener
        // trước và gọi $model->roles()->detach() trước khi observer này chạy.
        // Admin/staff không có data trên Firebase → Firebase DELETE node không tồn tại là no-op.
        Log::info("🗑️ [UserObserver] User #{$user->id} bị xóa → xóa Firebase");
        FirebaseRTDBService::deleteDriverProfile($user->id);
    }
}
