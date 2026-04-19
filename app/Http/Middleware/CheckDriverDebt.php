<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\DriverDebt;

class CheckDriverDebt
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Chỉ áp dụng cho tài xế
        if ($user && $user->hasRole('driver')) {

            // Nếu tài xế có công nợ quá hạn (overdue)
            $overdueDebts = DriverDebt::where('driver_id', $user->id)
                ->where('status', 'overdue')
                ->get();

            if ($overdueDebts->isNotEmpty()) {
                $totalOverdue = $overdueDebts->sum(fn($d) => $d->amount_due - $d->amount_paid);

                return response()->json([
                    'success' => false,
                    'type'    => 'debt_overdue',
                    'message' => 'Bạn đang có ' . number_format($totalOverdue, 0, ',', '.') . 'đ công nợ quá hạn. Vui lòng thanh toán để tiếp tục nhận đơn.',
                ], 403);
            }
        }


        return $next($request);
    }
}
