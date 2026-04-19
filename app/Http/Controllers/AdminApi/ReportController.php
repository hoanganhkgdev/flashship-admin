<?php

namespace App\Http\Controllers\AdminApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * 📊 Báo cáo doanh thu theo ngày (7 ngày gần nhất)
     */
    public function revenue(Request $request)
    {
        $cityId = $request->city_id;

        $query = DB::table('orders')
            ->selectRaw('DATE(created_at) as period, COUNT(*) as total_orders, SUM(shipping_fee) as total_revenue')
            ->when($cityId, fn($q) => $q->where('city_id', $cityId))
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('period')
            ->limit(7);

        $data = $query->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 📦 Báo cáo số lượng đơn hàng theo trạng thái
     */
    public function orders(Request $request)
    {
        $cityId = $request->city_id;

        $query = DB::table('orders')
            ->selectRaw('status, COUNT(*) as count')
            ->when($cityId, fn($q) => $q->where('city_id', $cityId))
            ->groupBy('status');

        $data = $query->get()->map(function ($row) {
            $labels = [
                'pending' => 'Chờ xử lý',
                'assigned' => 'Đã giao tài xế',
                'completed' => 'Hoàn thành',
                'cancelled' => 'Hủy',
            ];
            $row->label = $labels[$row->status] ?? $row->status;
            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 🚚 Báo cáo theo tài xế: tổng đơn và doanh thu
     */
    public function drivers(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
            'city_id'   => 'nullable|integer',
        ]);

        $from = $request->date('from_date');
        $to   = $request->date('to_date');

        $query = DB::table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.delivery_man_id')
            ->selectRaw('u.id, u.name, COUNT(o.id) as total_orders, SUM(o.shipping_fee) as total_revenue')
            ->where('o.status', 'completed')                 // chỉ tính đơn hoàn tất
            ->when($request->city_id, fn ($q, $cityId) => $q->where('o.city_id', $cityId))
            ->when($from && $to, fn ($q) => $q->whereBetween('o.updated_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ]))
            ->when($from && !$to, fn ($q) => $q->where('o.updated_at', '>=', $from->copy()->startOfDay()))
            ->when(!$from && $to, fn ($q) => $q->where('o.updated_at', '<=', $to->copy()->endOfDay()))
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_revenue');

        $drivers = $query->get();

        return response()->json([
            'success' => true,
            'data' => $drivers,
            'summary' => [
                'total_drivers' => $drivers->count(),
                'total_orders'  => $drivers->sum('total_orders'),
                'total_revenue' => $drivers->sum('total_revenue'),
            ],
        ]);
    }


}