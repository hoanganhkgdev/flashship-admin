<?php

namespace App\Http\Controllers;

use App\Exports\DriverCashReportExport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DriverCashExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']),
            403
        );

        $tz     = 'Asia/Ho_Chi_Minh';
        $mode   = in_array($request->input('mode'), ['month', 'day', 'range']) ? $request->input('mode') : 'month';
        $cityId = $request->input('city_id') ? (int) $request->input('city_id') : null;
        $search = (string) $request->input('search', '');

        $user = auth()->user();
        if ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $cityId = $user->city_id;
        }

        if ($mode === 'month') {
            $rawMonth = $request->input('month', now($tz)->format('Y-m'));
            $start    = Carbon::parse($rawMonth . '-01', $tz)->startOfMonth();
            $end      = $start->copy()->endOfMonth();
            $period   = $start->format('m/Y');
            $suffix   = 'thang-' . $start->format('Y-m');
        } elseif ($mode === 'range') {
            $start  = Carbon::parse($request->input('from', now($tz)->startOfMonth()->toDateString()), $tz)->startOfDay();
            $end    = Carbon::parse($request->input('until', now($tz)->toDateString()), $tz)->endOfDay();
            if ($end->lt($start)) $end = $start->copy()->endOfDay();
            $period = $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y');
            $suffix = 'tu-' . $start->toDateString() . '-den-' . $end->toDateString();
        } else {
            $rawDate = $request->input('date', now($tz)->toDateString());
            $start   = Carbon::parse($rawDate, $tz)->startOfDay();
            $end     = $start->copy()->endOfDay();
            $period  = $start->format('d/m/Y');
            $suffix  = 'ngay-' . $start->toDateString();
        }

        $debtSub = DB::table('driver_debts')
            ->selectRaw('driver_id, SUM(COALESCE(amount_paid, 0)) as thu')
            ->where('amount_paid', '>', 0)
            ->whereBetween('updated_at', [$start, $end])
            ->groupBy('driver_id');

        $withdrawSub = DB::table('withdraw_requests')
            ->selectRaw('driver_id, SUM(COALESCE(amount, 0)) as chi')
            ->where('status', 'approved')
            ->whereBetween('updated_at', [$start, $end])
            ->groupBy('driver_id');

        $query = User::role('driver')
            ->leftJoinSub($debtSub, 'd', 'd.driver_id', '=', 'users.id')
            ->leftJoinSub($withdrawSub, 'w', 'w.driver_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name,
                users.phone,
                COALESCE(d.thu, 0) as thu,
                COALESCE(w.chi, 0) as chi
            ')
            ->where('users.status', 1)
            ->where(function ($q) {
                $q->whereRaw('COALESCE(d.thu, 0) > 0')
                  ->orWhereRaw('COALESCE(w.chi, 0) > 0');
            })
            ->orderByRaw('thu desc, users.id asc');

        if ($cityId) {
            $query->where('users.city_id', $cityId);
        }

        if ($search !== '') {
            $s = "%{$search}%";
            $query->where(fn($q) => $q
                ->where('users.name', 'like', $s)
                ->orWhere('users.phone', 'like', $s)
            );
        }

        $data = $query->get()->map(function ($row, $i) {
            $row->stt = $i + 1;
            return $row;
        });

        $filename = "thu-chi-tai-xe-{$suffix}.xlsx";

        return Excel::download(new DriverCashReportExport($data, $period), $filename);
    }
}
