<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Nếu chưa duyệt
            if ($user->status == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản chưa được duyệt. Vui lòng chờ admin xác nhận.',
                ], 403);
            }

            // Nếu bị khóa
            if ($user->status == 2) {
                // Xóa token hiện tại để force logout
                if ($request->user()->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ admin.',
                ], 403);
            }
            // 🚫 FORCE OFFLINE nếu hết ca đối với gói tuần (weekly)
            if ($user->hasRole('driver') && $user->is_online && !$user->isInShift()) {
                $user->update(['is_online' => false]);
                \Log::info("📴 Tài xế #{$user->id} bị ép offline do ngoài ca (Middleware CheckUserStatus)");

                // 🔥 Sync Firebase để App & Admin map cập nhật ngay lập tức
                try {
                    \App\Services\FirebaseRTDBService::publishDriverProfile($user->load(['shifts', 'plan']));
                    \App\Services\FirebaseRTDBService::deleteDriverLocation($user->id);
                } catch (\Throwable $e) {
                    \Log::error("Firebase sync lỗi khi force-offline tài xế #{$user->id}: " . $e->getMessage());
                }
            }
        }

        return $next($request);
    }
}
