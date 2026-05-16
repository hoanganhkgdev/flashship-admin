<x-filament-panels::page>
    @php
        $stats   = $this->getStats();
        $rows    = $this->getData();
        $isAdmin = auth()->user()->hasRole('admin');
        $cities  = $isAdmin ? $this->getCities() : collect();
        $years   = $this->getAvailableYears();
        $mode    = $this->mode;

        $rate = $stats['total_orders'] > 0
            ? round($stats['completed_orders'] / $stats['total_orders'] * 100)
            : 0;

        $thClass = 'px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap';
        $tdClass = 'px-4 py-3 text-sm';
    @endphp

    {{-- ── Summary cards ──────────────────────────────────────────────── --}}
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">

        <div style="flex:1;min-width:160px" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng doanh thu</p>
            <p class="text-xl font-bold text-primary-600 dark:text-primary-400 tabular-nums">{{ number_format($stats['total_revenue']) }}₫</p>
            <p class="text-xs text-gray-400">ship: {{ number_format($stats['total_ship_fee']) }}₫ · bonus: {{ number_format($stats['total_bonus_fee']) }}₫</p>
        </div>

        <div style="flex:1;min-width:140px" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng đơn</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($stats['total_orders']) }}</p>
            <p class="text-xs text-gray-400">tỉ lệ HT: <span class="font-semibold text-emerald-600">{{ $rate }}%</span></p>
        </div>

        <div style="flex:1;min-width:130px" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Hoàn thành</p>
            <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ number_format($stats['completed_orders']) }}</p>
            <p class="text-xs text-gray-400">đơn</p>
        </div>

        <div style="flex:1;min-width:130px" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Đã hủy</p>
            <p class="text-xl font-bold text-red-500 dark:text-red-400 tabular-nums">{{ number_format($stats['cancelled_orders']) }}</p>
            <p class="text-xs text-gray-400">đơn</p>
        </div>

    </div>

    {{-- ── Controls ────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- Mode toggle --}}
        <div class="inline-flex rounded-lg overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700">
            <button wire:click="$set('mode', 'month')"
                class="px-4 py-2 text-sm font-medium transition-colors
                    {{ $mode === 'month'
                        ? 'bg-primary-600 text-white'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Theo tháng
            </button>
            <button wire:click="$set('mode', 'week')"
                class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-200 dark:border-gray-700
                    {{ $mode === 'week'
                        ? 'bg-primary-600 text-white'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Theo tuần
            </button>
        </div>

        {{-- Month mode: year picker --}}
        @if ($mode === 'month')
            <select wire:model.live="year"
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                       text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                       focus:outline-none focus:ring-2 focus:ring-primary-500">
                @foreach ($years as $y => $label)
                    <option value="{{ $y }}">{{ $label }}</option>
                @endforeach
            </select>
        @endif

        {{-- Week mode: date range --}}
        @if ($mode === 'week')
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500 dark:text-gray-400">Từ</label>
                <input type="date" wire:model.live="from"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                           text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                           focus:outline-none focus:ring-2 focus:ring-primary-500">
                <label class="text-sm text-gray-500 dark:text-gray-400">đến</label>
                <input type="date" wire:model.live="until"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                           text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                           focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>

            {{-- Week quick shortcuts --}}
            <div class="inline-flex rounded-lg overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700">
                <button onclick="
                        @this.set('from', '{{ now()->subWeeks(3)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString() }}');
                        @this.set('until', '{{ now()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString() }}');
                    "
                    class="px-3 py-2 text-xs font-medium bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                           hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border-r border-gray-200 dark:border-gray-700">
                    4 tuần
                </button>
                <button onclick="
                        @this.set('from', '{{ now()->subWeeks(11)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString() }}');
                        @this.set('until', '{{ now()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString() }}');
                    "
                    class="px-3 py-2 text-xs font-medium bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                           hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border-r border-gray-200 dark:border-gray-700">
                    12 tuần
                </button>
                <button onclick="
                        @this.set('from', '{{ now()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString() }}');
                        @this.set('until', '{{ now()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString() }}');
                    "
                    class="px-3 py-2 text-xs font-medium bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                           hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Tháng này
                </button>
            </div>
        @endif

        {{-- City filter --}}
        @if ($isAdmin)
            <select wire:model.live="cityId"
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                       text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                       focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">Tất cả khu vực</option>
                @foreach ($cities as $city)
                    <option value="{{ $city->id }}">{{ $city->name }}</option>
                @endforeach
            </select>
        @endif

    </div>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
                        <th class="{{ $thClass }} w-10 text-center">STT</th>
                        <th class="{{ $thClass }}">Kỳ báo cáo</th>
                        <th class="{{ $thClass }} text-center">Tổng đơn</th>
                        <th class="{{ $thClass }} text-center">Hoàn thành</th>
                        <th class="{{ $thClass }} text-center">Đã hủy</th>
                        <th class="{{ $thClass }} text-center">Tỉ lệ HT</th>
                        <th class="{{ $thClass }} text-right">Doanh thu ship</th>
                        <th class="{{ $thClass }} text-right">Bonus</th>
                        <th class="{{ $thClass }} text-right">Tổng doanh thu</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rows as $i => $row)
                        @php
                            $rowRate = $row->total_orders > 0
                                ? round($row->completed_orders / $row->total_orders * 100)
                                : 0;

                            if ($mode === 'month') {
                                $periodLabel = 'Tháng ' . $row->mo . '/' . $row->yr;
                                $periodSub   = null;
                            } else {
                                $wStart = \Carbon\Carbon::parse($row->week_start)->format('d/m');
                                $wEnd   = \Carbon\Carbon::parse($row->week_end)->format('d/m/Y');
                                $periodLabel = "Tuần {$row->week_num}/{$row->yr}";
                                $periodSub   = "{$wStart} – {$wEnd}";
                            }
                        @endphp
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors">

                            <td class="px-4 py-3 text-center text-xs text-gray-400 tabular-nums">
                                {{ $i + 1 }}
                            </td>

                            <td class="{{ $tdClass }}">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $periodLabel }}</span>
                                @if ($periodSub)
                                    <br><span class="text-xs text-gray-400">{{ $periodSub }}</span>
                                @endif
                            </td>

                            <td class="{{ $tdClass }} text-center font-bold text-gray-900 dark:text-white tabular-nums">
                                {{ number_format($row->total_orders) }}
                            </td>

                            <td class="{{ $tdClass }} text-center tabular-nums">
                                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                                    <x-heroicon-m-check-badge class="h-3.5 w-3.5" />
                                    {{ number_format($row->completed_orders) }}
                                </span>
                            </td>

                            <td class="{{ $tdClass }} text-center tabular-nums">
                                @if ($row->cancelled_orders > 0)
                                    <span class="text-red-500 dark:text-red-400">{{ number_format($row->cancelled_orders) }}</span>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>

                            <td class="{{ $tdClass }} text-center">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums
                                    {{ $rowRate >= 80 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : ($rowRate >= 50 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400') }}">
                                    {{ $rowRate }}%
                                </span>
                            </td>

                            <td class="{{ $tdClass }} text-right tabular-nums font-semibold text-gray-900 dark:text-white">
                                {{ number_format($row->total_ship_fee) }}₫
                            </td>

                            <td class="{{ $tdClass }} text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $row->total_bonus_fee > 0 ? number_format($row->total_bonus_fee) . '₫' : '—' }}
                            </td>

                            <td class="{{ $tdClass }} text-right tabular-nums font-bold text-primary-600 dark:text-primary-400">
                                {{ number_format($row->total_revenue) }}₫
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                                Không có dữ liệu trong khoảng thời gian này
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/50 font-semibold">
                            <td colspan="2" class="px-4 py-3 text-sm text-gray-500">
                                Tổng ({{ $rows->count() }} {{ $mode === 'month' ? 'tháng' : 'tuần' }})
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-gray-900 dark:text-white">
                                {{ number_format($rows->sum('total_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ number_format($rows->sum('completed_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-red-500 dark:text-red-400">
                                {{ number_format($rows->sum('cancelled_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm">
                                @php
                                    $footerTotal = $rows->sum('total_orders');
                                    $footerRate  = $footerTotal > 0
                                        ? round($rows->sum('completed_orders') / $footerTotal * 100)
                                        : 0;
                                @endphp
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $footerRate >= 80 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                    {{ $footerRate }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-900 dark:text-white">
                                {{ number_format($rows->sum('total_ship_fee')) }}₫
                            </td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-500">
                                {{ number_format($rows->sum('total_bonus_fee')) }}₫
                            </td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-primary-600 dark:text-primary-400">
                                {{ number_format($rows->sum('total_revenue')) }}₫
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</x-filament-panels::page>
