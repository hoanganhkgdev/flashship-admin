<x-filament-panels::page>
    @php
        $stats   = $this->getStats();
        $rows    = $this->getData();
        $period  = $this->getPeriodLabel();
        $isAdmin = auth()->user()->hasRole('admin');
        $cities  = $isAdmin ? $this->getCities() : collect();

        $rate = $stats['total_orders'] > 0
            ? round($stats['completed_orders'] / $stats['total_orders'] * 100)
            : 0;

        $thClass = 'px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap';
        $thSort  = $thClass . ' cursor-pointer select-none hover:text-gray-900 dark:hover:text-white transition-colors';
        $tdClass = 'px-4 py-3 text-sm';
    @endphp

    {{-- ── Summary cards ──────────────────────────────────────────────── --}}
    <div style="display:flex; gap:0.75rem;">

        <div style="flex:1;min-width:0" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng đơn · {{ $period }}</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($stats['total_orders']) }}</p>
            <p class="text-xs text-gray-400">tỉ lệ HT: <span class="font-semibold text-emerald-600">{{ $rate }}%</span></p>
        </div>

        <div style="flex:1;min-width:0" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Hoàn thành</p>
            <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ number_format($stats['completed_orders']) }}</p>
            <p class="text-xs text-gray-400">đơn</p>
        </div>

        <div style="flex:1;min-width:0" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Đã hủy</p>
            <p class="text-xl font-bold text-red-500 dark:text-red-400 tabular-nums">{{ number_format($stats['cancelled_orders']) }}</p>
            <p class="text-xs text-gray-400">đơn</p>
        </div>

        <div style="flex:1;min-width:0" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Doanh thu ship</p>
            <p class="text-xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">{{ number_format($stats['total_ship']) }}₫</p>
            <p class="text-xs text-gray-400">bonus: {{ number_format($stats['total_bonus']) }}₫</p>
        </div>

    </div>

    {{-- ── Controls ────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3">

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

        {{-- Quick shortcuts --}}
        <div class="inline-flex rounded-lg overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700 ml-auto">
            <button wire:click="$set('from', '{{ now()->startOfMonth()->toDateString() }}')"
                    wire:click.chain="$set('until', '{{ now()->endOfMonth()->toDateString() }}')"
                onclick="
                    @this.set('from', '{{ now()->startOfMonth()->toDateString() }}');
                    @this.set('until', '{{ now()->endOfMonth()->toDateString() }}');
                "
                class="px-3 py-2 text-xs font-medium bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                       hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border-r border-gray-200 dark:border-gray-700">
                Tháng này
            </button>
            <button onclick="
                    @this.set('from', '{{ now()->subMonth()->startOfMonth()->toDateString() }}');
                    @this.set('until', '{{ now()->subMonth()->endOfMonth()->toDateString() }}');
                "
                class="px-3 py-2 text-xs font-medium bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                       hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border-r border-gray-200 dark:border-gray-700">
                Tháng trước
            </button>
            <button onclick="
                    @this.set('from', '{{ now()->toDateString() }}');
                    @this.set('until', '{{ now()->toDateString() }}');
                "
                class="px-3 py-2 text-xs font-medium bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                       hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                Hôm nay
            </button>
        </div>

    </div>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
                        <th class="{{ $thClass }} w-12 text-center">STT</th>

                        <th wire:click="toggleSort('report_date')" class="{{ $thSort }}">
                            Ngày
                            @if ($sortBy === 'report_date')
                                <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th wire:click="toggleSort('total_orders')" class="{{ $thSort }} text-center">
                            Tổng đơn
                            @if ($sortBy === 'total_orders')
                                <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th wire:click="toggleSort('completed_orders')" class="{{ $thSort }} text-center">
                            Hoàn thành
                            @if ($sortBy === 'completed_orders')
                                <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th class="{{ $thClass }} text-center">Đang giao</th>
                        <th class="{{ $thClass }} text-center">Chờ xử lý</th>

                        <th wire:click="toggleSort('cancelled_orders')" class="{{ $thSort }} text-center">
                            Đã hủy
                            @if ($sortBy === 'cancelled_orders')
                                <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th class="{{ $thClass }} text-center">Tỉ lệ HT</th>

                        <th wire:click="toggleSort('total_ship_fee')" class="{{ $thSort }} text-right">
                            Doanh thu ship
                            @if ($sortBy === 'total_ship_fee')
                                <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th class="{{ $thClass }} text-right">Bonus</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rows as $i => $row)
                        @php
                            $rowRate = $row->total_orders > 0
                                ? round($row->completed_orders / $row->total_orders * 100)
                                : 0;
                        @endphp
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors">

                            <td class="px-4 py-3 text-center text-xs text-gray-400 tabular-nums">
                                {{ $rows->firstItem() + $i }}
                            </td>

                            <td class="{{ $tdClass }} font-semibold text-gray-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-m-calendar class="h-3.5 w-3.5 text-gray-400 shrink-0" />
                                    {{ \Carbon\Carbon::parse($row->report_date)->format('d/m/Y') }}
                                    <span class="text-[10px] font-normal text-gray-400">
                                        {{ \Carbon\Carbon::parse($row->report_date)->locale('vi')->isoFormat('ddd') }}
                                    </span>
                                </div>
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

                            <td class="{{ $tdClass }} text-center tabular-nums text-amber-600 dark:text-amber-400">
                                {{ $row->assigned_orders > 0 ? number_format($row->assigned_orders) : '—' }}
                            </td>

                            <td class="{{ $tdClass }} text-center tabular-nums text-sky-600 dark:text-sky-400">
                                {{ $row->pending_orders > 0 ? number_format($row->pending_orders) : '—' }}
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

                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                                Không có dữ liệu trong khoảng thời gian này
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/50 font-semibold">
                            <td colspan="2" class="px-4 py-3 text-sm text-gray-500">Tổng ({{ $rows->count() }} ngày)</td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-gray-900 dark:text-white">
                                {{ number_format($rows->sum('total_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ number_format($rows->sum('completed_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-amber-600 dark:text-amber-400">
                                {{ number_format($rows->sum('assigned_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-sky-600 dark:text-sky-400">
                                {{ number_format($rows->sum('pending_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm tabular-nums text-red-500 dark:text-red-400">
                                {{ number_format($rows->sum('cancelled_orders')) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm">
                                @php
                                    $footerTotal = $rows->sum('total_orders');
                                    $footerRate  = $footerTotal > 0 ? round($rows->sum('completed_orders') / $footerTotal * 100) : 0;
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
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

</x-filament-panels::page>
