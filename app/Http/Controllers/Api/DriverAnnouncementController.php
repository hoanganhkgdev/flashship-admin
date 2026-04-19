<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DriverAnnouncementController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user(); // tài xế đã đăng nhập

        // Lấy thông báo admin theo khu vực (city_id) của driver
        $announcement = Announcement::query()
            ->whereIn('audience', ['driver', 'all'])
            ->where(function ($q) use ($user) {
                // Thông báo cho tất cả khu vực (city_id = null) hoặc thông báo cho khu vực của driver
                $q->whereNull('city_id')
                  ->orWhere('city_id', $user->city_id);
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->latest()
            ->first();

        if ($announcement) {
            return response()->json([
                'success' => true,
                'data' => [
                    'type'    => $announcement->level ?? 'info',
                    'message' => $announcement->message,
                ],
            ]);
        }

        // Không có thông báo
        return response()->json([
            'success' => true,
            'data'    => null,
        ]);
    }
}