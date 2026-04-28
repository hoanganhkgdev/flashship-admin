<?php

namespace App\Observers;

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

        try {
            FirebaseRTDBService::publishDriverProfile($user->fresh(['shifts', 'plan']));
        } catch (\Throwable $e) {
            Log::error("❌ [UserObserver] Firebase sync lỗi driver #{$user->id}: " . $e->getMessage());
        }
    }
}
