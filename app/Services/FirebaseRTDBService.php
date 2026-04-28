<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseRTDBService
{
    protected static function getDatabaseUrl(): string
    {
        return rtrim(config('services.firebase.database_url'), '/');
    }

    /**
     * Prefix namespace theo môi trường để local và production
     * không đụng chung data dù dùng chung Firebase project.
     * Production: /flashship/...
     * Local/Staging: /flashship_local/... hoặc /flashship_staging/...
     */
    protected static function ns(): string
    {
        return 'flashship_main';
    }

    protected static function getAccessToken(): ?string
    {
        return FirebaseServiceAccount::getAccessToken(FirebaseServiceAccount::SCOPE_DATABASE);
    }

    /**
     * Ghi đơn hàng vào RTDB khi status = pending
     * Path: /flashship/orders/city_{city_id}/order_{id}
     */
    public static function publishOrder($order): void
    {
        try {
            $token = static::getAccessToken();
            if (!$token) return;

            $path = "/" . static::ns() . "/orders/city_{$order->city_id}/order_{$order->id}.json";
            $url  = static::getDatabaseUrl() . $path;

            $response = Http::withToken($token)->put($url, [
                'id'               => $order->id,
                'status'           => $order->status,
                'city_id'          => $order->city_id,
                'delivery_man_id'  => $order->delivery_man_id,
                'pickup_address'   => $order->pickup_address,
                'delivery_address' => $order->delivery_address,
                'sender_name'      => $order->sender_name   ?? $order->pickup_name   ?? '',
                'sender_phone'     => $order->sender_phone  ?? $order->pickup_phone  ?? '',
                'recipient_name'   => $order->recipient_name ?? $order->delivery_name ?? '',
                'recipient_phone'  => $order->recipient_phone ?? $order->delivery_phone ?? '',
                'shipping_fee'     => (float) ($order->shipping_fee ?? 0),
                'bonus_fee'        => (float) ($order->bonus_fee ?? 0),
                'note'             => $order->order_note ?? $order->note ?? '',
                'service_type'     => $order->service_type ?? '',
                'is_freeship'      => (bool) ($order->is_freeship ?? false),
                'created_at'       => $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'updated_at'       => now()->toIso8601String(),
            ]);

            if ($response->successful()) {
                Log::info("✅ RTDB: Published order #{$order->id} → city_{$order->city_id}");
            } else {
                Log::error("❌ RTDB: publishOrder failed #{$order->id}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("❌ RTDB: publishOrder exception #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Xóa đơn khỏi RTDB khi assigned / cancelled / completed / deleted
     */
    public static function removeOrder($order): void
    {
        try {
            $token = static::getAccessToken();
            if (!$token) return;

            $path = "/" . static::ns() . "/orders/city_{$order->city_id}/order_{$order->id}.json";
            $url  = static::getDatabaseUrl() . $path;

            $response = Http::withToken($token)->delete($url);

            if ($response->successful()) {
                Log::info("🗑️ RTDB: Removed order #{$order->id}");
            } else {
                Log::error("❌ RTDB: removeOrder failed #{$order->id}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("❌ RTDB: removeOrder exception #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * 🚀 Đẩy trạng thái tài xế (shifts + is_online) lên Firebase RTDB
     * Gọi sau khi admin lưu chỉnh sửa tài xế (đổi ca, khoá, duyệt, v.v.)
     *
     * Path: /flashship/drivers/driver_{id}.json
     * App Flutter lắng nghe path này qua listenDriverStatus()
     */
    /**
     * 🚀 Push toàn bộ profile tài xế lên Firebase RTDB
     * Format giống hệt response của getMe() API để app đọc trực tiếp.
     * Gọi khi: admin đổi ca, admin lưu tài xế, tài xế toggle online/offline.
     *
     * Path: /flashship/drivers/driver_{id}.json
     */
    public static function publishDriverProfile($driver): void
    {
        try {
            $token = static::getAccessToken();
            if (!$token) {
                Log::warning("⚠️ RTDB publishDriverProfile: Không lấy được token.");
                return;
            }

            $driver->loadMissing(['shifts', 'plan']);

            // Dùng object keyed (shift_1, shift_2) thay vì array để tránh Firebase
            // convert array → object với numeric key khi read từ Flutter SDK
            $shifts = [];
            foreach ($driver->shifts as $shift) {
                $shifts["shift_{$shift->id}"] = [
                    'id'         => (int)    $shift->id,
                    'code'       => (string) ($shift->code ?? ''),
                    'name'       => (string) ($shift->name ?? ''),
                    'start_time' => (string) ($shift->start_time ?? ''),
                    'end_time'   => (string) ($shift->end_time   ?? ''),
                ];
            }

            $payload = [
                'id'                        => (int)    $driver->id,
                'name'                      => (string) $driver->name,
                'phone'                     => (string) $driver->phone,
                'city_id'                   => (int)    ($driver->city_id ?? 0),
                'is_online'                 => (bool)   $driver->is_online,
                'is_active'                 => $driver->status == 1,
                'unread_notifications_count'=> $driver->unreadNotifications()->count(),
                'plan_type'                 => (string) ($driver->plan?->type ?? 'commission'),
                'custom_commission_rate'    => $driver->custom_commission_rate !== null ? (float) $driver->custom_commission_rate : null,
                'shifts'                    => empty($shifts) ? (object)[] : $shifts,
                'updated_at'                => now()->toIso8601String(),
            ];

            $path = "/" . static::ns() . "/drivers/driver_{$driver->id}.json";
            $url  = static::getDatabaseUrl() . $path;

            $response = Http::withToken($token)->asJson()->put($url, $payload);

            if ($response->successful()) {
                Log::info("✅ RTDB: PublishDriverProfile driver#{$driver->id} — " . count($shifts) . " shifts");
            } else {
                Log::error("❌ RTDB: publishDriverProfile failed driver#{$driver->id}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("❌ RTDB: publishDriverProfile exception driver#{$driver->id}: " . $e->getMessage());
        }
    }
    /**
     * 📍 Đẩy vị trí tài xế (lat, lng) lên Firebase RTDB
     * Path: /flashship/locations/driver_{id}.json
     */
    public static function publishDriverLocation($driverId, $lat, $lng): void
    {
        try {
            $token = static::getAccessToken();
            if (!$token) {
                Log::error("❌ RTDB: publishDriverLocation failed - No Access Token");
                return;
            }

            $payload = [
                'id'         => (int) $driverId,
                'lat'        => (float) $lat,
                'lng'        => (float) $lng,
                'updated_at' => now()->toIso8601String(),
            ];

            $path = "/" . static::ns() . "/locations/driver_{$driverId}.json";
            $url  = static::getDatabaseUrl() . $path;

            $response = Http::withToken($token)->asJson()->patch($url, $payload);
            
            if ($response->successful()) {
                Log::info("📍 RTDB: Đã đẩy vị trí tài xế #{$driverId} lên Firebase thành công.");
            } else {
                Log::error("❌ RTDB: publishDriverLocation failed driver#{$driverId}: " . $response->body());
            }
            
        } catch (\Throwable $e) {
            Log::error("❌ RTDB: publishDriverLocation exception driver#{$driverId}: " . $e->getMessage());
        }
    }

    public static function deleteDriverLocation($driverId): void
    {
        try {
            $token = static::getAccessToken();
            if (!$token) return;

            $path = "/" . static::ns() . "/locations/driver_{$driverId}.json";
            $url  = static::getDatabaseUrl() . $path;

            Http::withToken($token)->delete($url);
        } catch (\Throwable $e) {
            Log::error("❌ RTDB: deleteDriverLocation exception driver#{$driverId}: " . $e->getMessage());
        }
    }
}
