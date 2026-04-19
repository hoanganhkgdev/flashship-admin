<?php

namespace App\Http\Controllers\AdminApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DriverController extends Controller
{
    /**
     * Danh sách tài xế
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->role('driver')
            ->with(['city:id,name', 'shift:id,name'])
            ->withCount('orders')
            ->latest();

        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        $drivers = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $drivers,
        ]);
    }

    /**
     * Dữ liệu tài xế cho bản đồ real-time
     */
    public function mapData(Request $request)
    {
        $cityId = $request->query('city_id') ?: session('current_city_id');

        $query = User::role('driver')
            ->with(['city:id,name', 'shifts', 'plan:id,type'])
            ->withCount([
                'orders as active_orders_count' => fn($q) => $q
                    ->withoutGlobalScopes()
                    ->whereIn('status', ['assigned', 'delivering']),
            ])
            ->where('is_online', true); // ✅ Chỉ lấy tài xế đang online (vị trí lấy từ Firebase, không cần lat/lng trong MySQL)

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        $drivers = $query->get(['id', 'name', 'phone', 'city_id', 'latitude', 'longitude', 'is_online', 'last_location_update', 'profile_photo_path', 'custom_commission_rate', 'plan_id'])
            ->filter(function ($u) {
                // ✅ Lọc thêm: chỉ hiển thị tài xế đang trong ca làm việc
                return $u->isInShift();
            })
            ->map(function ($u) {
                $lastSeen = $u->last_location_update
                    ? Carbon::parse($u->last_location_update)->diffForHumans()
                    : 'Chưa rõ';

                $avatarUrl = $u->profile_photo_path
                    ? asset('storage/' . $u->profile_photo_path)
                    : 'https://ui-avatars.com/api/?name=' . urlencode($u->name) . '&color=FFFFFF&background=0284c7&size=80';

                return [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'phone'        => $u->phone,
                    'city'         => $u->city?->name ?? 'N/A',
                    'lat'          => (float) $u->latitude,
                    'lng'          => (float) $u->longitude,
                    'is_online'    => (bool) $u->is_online,
                    'active_orders'=> (int) $u->active_orders_count,
                    'last_seen'    => $lastSeen,
                    'last_update'  => $u->last_location_update ? Carbon::parse($u->last_location_update)->toIso8601String() : null,
                    'avatar_url'   => $avatarUrl,
                ];
            })->values();

        return response()->json([
            'success' => true,
            'data'    => $drivers,
            'stats'   => [
                'total'   => $drivers->count(),
                'online'  => $drivers->where('is_online', true)->count(),
                'offline' => $drivers->where('is_online', false)->count(),
                'busy'    => $drivers->where('active_orders', '>', 0)->count(),
            ],
        ]);
    }
}