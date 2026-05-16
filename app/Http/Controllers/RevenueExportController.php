<?php

namespace App\Http\Controllers;

use App\Exports\RevenueReportExport;
use App\Services\RevenueReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RevenueExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']),
            403
        );

        $tz     = 'Asia/Ho_Chi_Minh';
        $mode   = in_array($request->input('mode'), ['month', 'week']) ? $request->input('mode') : 'month';
        $year   = (int) ($request->input('year') ?: now($tz)->format('Y'));
        $from   = $request->input('from') ?: now($tz)->subWeeks(11)->startOfWeek(Carbon::MONDAY)->toDateString();
        $until  = $request->input('until') ?: now($tz)->endOfWeek(Carbon::SUNDAY)->toDateString();
        $cityId = $request->input('city_id') ? (int) $request->input('city_id') : null;

        $user = auth()->user();
        if ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $cityId = $user->city_id;
        }

        $data = $mode === 'month'
            ? RevenueReportService::monthData($year, $cityId)
            : RevenueReportService::weekData($from, $until, $cityId);

        $suffix   = $mode === 'month' ? "thang-{$year}" : "tuan-{$from}-{$until}";
        $filename = "doanh-thu-{$suffix}.xlsx";

        return Excel::download(new RevenueReportExport($data, $mode), $filename);
    }
}
