<?php

namespace App\Http\Controllers\AdminApi;

use App\Http\Controllers\Controller;
use App\Models\DriverDebt;
use App\Models\DriverWallet;
use App\Models\DriverPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceController extends Controller
{
    public function debts(Request $request): JsonResponse
    {
        $debts = $this->baseQuery(DriverDebt::query(), $request)
            ->orderByDesc('week_start')
            ->get()
            ->filter(fn ($debt) => $debt->driver) // loại bản ghi thiếu driver
            ->map(function ($debt) {
                $debt->week_label = $this->formatWeekRange(
                    $debt->week_start,
                    $debt->week_end
                );

                return $debt;
            });

        return response()->json([
            'success' => true,
            'data'    => $debts,
            'summary' => [
                'total_records' => $debts->count(),
                'total_amount'  => $debts->sum('amount_due'),
            ],
        ]);
    }

    public function wallets(Request $request): JsonResponse
    {
        $wallets = $this->baseQuery(DriverWallet::query(), $request)
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($wallet) => $wallet->driver);

        return response()->json([
            'success' => true,
            'data'    => $wallets,
            'summary' => [
                'total_records' => $wallets->count(),
                'total_balance' => $wallets->sum('balance'),
            ],
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $payouts = $this->baseQuery(DriverPayout::query(), $request)
            ->latest()
            ->get()
            ->filter(fn ($payout) => $payout->driver);

        return response()->json([
            'success' => true,
            'data'    => $payouts,
            'summary' => [
                'total_records' => $payouts->count(),
                'total_amount'  => $payouts->sum('amount'),
                'by_status'     => $payouts->groupBy('status')
                    ->map(fn (Collection $items) => $items->count()),
            ],
        ]);
    }

    /**
     * Áp dụng eager load driver + filter city_id (nếu có).
     */
    protected function baseQuery($query, Request $request)
    {
        return $query
            ->with(['driver:id,name,phone,city_id', 'driver.city:id,name'])
            ->when(
                $request->filled('city_id'),
                fn ($q) => $q->whereHas(
                    'driver',
                    fn ($driverQuery) => $driverQuery->where('city_id', $request->city_id)
                )
            );
    }

    /**
     * Format tuần, báo null nếu không parse được.
     */
    protected function formatWeekRange($start, $end): ?string
    {
        try {
            if (!$start || !$end) {
                return null;
            }

            $startDate = Carbon::parse($start);
            $endDate   = Carbon::parse($end);

            return $startDate->format('d/m') . ' - ' . $endDate->format('d/m');
        } catch (\Throwable $e) {
            // log để điều tra dữ liệu không hợp lệ
            logger()->warning('Debt có tuần không hợp lệ', [
                'start' => $start,
                'end'   => $end,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
