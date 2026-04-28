<x-filament-panels::page>
    @php
        $stats   = $this->getStats();
        $drivers = $this->getData();
        $period  = $this->getPeriodLabel();
        $isAdmin = auth()->user()->hasRole('admin');
        $cities  = $isAdmin ? $this->getCities() : collect();

        $thClass  = 'px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap';
        $thSort   = $thClass . ' cursor-pointer select-none hover:text-gray-900 dark:hover:text-white transition-colors';
        $tdClass  = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-300';
        $numClass = 'px-4 py-3 text-sm tabular-nums text-right';
    @endphp

    {{-- ── Summary bar ─────────────────────────────────────────────────── --}}
    <div style="display:flex; gap:0.75rem;">

        <div style="flex:1; min-width:0;" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng đơn · {{ $period }}</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($stats['total_orders']) }}</p>
            <p class="text-xs text-gray-400">{{ $stats['active_drivers'] }} tài xế</p>
        </div>

        <div style="flex:1; min-width:0;" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng ship</p>
            <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ number_format($stats['total_ship']) }}₫</p>
            <p class="text-xs text-gray-400">phí vận chuyển</p>
        </div>

        <div style="flex:1; min-width:0;" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng bonus</p>
            <p class="text-xl font-bold text-violet-600 dark:text-violet-400 tabular-nums">{{ number_format($stats['total_bonus']) }}₫</p>
            <p class="text-xs text-gray-400">thưởng thêm</p>
        </div>

        <div style="flex:1; min-width:0;" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng thu nhập</p>
            <p class="text-xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">{{ number_format($stats['total_income']) }}₫</p>
            <p class="text-xs text-gray-400">ship + bonus</p>
        </div>

    </div>

    {{-- ── Controls ────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- Mode toggle --}}
        <div class="inline-flex rounded-lg overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700">
            <button wire:click="$set('mode','month')"
                class="px-4 py-2 text-sm font-medium transition-colors
                    {{ $mode === 'month'
                        ? 'bg-primary-600 text-white'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Theo tháng
            </button>
            <button wire:click="$set('mode','day')"
                class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-200 dark:border-gray-700
                    {{ $mode === 'day'
                        ? 'bg-primary-600 text-white'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Theo ngày
            </button>
        </div>

        {{-- Date / Month picker --}}
        @if ($mode === 'month')
            <input type="month" wire:model.live="month"
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                       text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                       focus:outline-none focus:ring-2 focus:ring-primary-500">
        @else
            <input type="date" wire:model.live="date"
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                       text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                       focus:outline-none focus:ring-2 focus:ring-primary-500">
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

        {{-- Search --}}
        <div class="relative flex-1 min-w-[200px]">
            <x-heroicon-m-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="search"
                placeholder="Tìm tên hoặc SĐT..."
                class="w-full pl-9 pr-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                       bg-white dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-300
                       shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>

    </div>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
                        <th class="{{ $thClass }} w-10 text-center">#</th>
                        <th class="{{ $thClass }}">Tài xế</th>
                        <th class="{{ $thClass }}">SĐT</th>

                        <th wire:click="toggleSort('orders')" class="{{ $thSort }} text-right">
                            Đơn
                            @if ($sortBy === 'orders')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th wire:click="toggleSort('ship')" class="{{ $thSort }} text-right">
                            Ship
                            @if ($sortBy === 'ship')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th wire:click="toggleSort('bonus')" class="{{ $thSort }} text-right">
                            Bonus
                            @if ($sortBy === 'bonus')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th wire:click="toggleSort('total')" class="{{ $thSort }} text-right">
                            Tổng thu nhập
                            @if ($sortBy === 'total')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($drivers as $i => $driver)
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors">

                            <td class="px-4 py-3 text-center text-xs text-gray-400 tabular-nums">
                                {{ $drivers->firstItem() + $i }}
                            </td>

                            <td class="{{ $tdClass }} font-medium text-gray-900 dark:text-white">
                                {{ $driver->name }}
                            </td>

                            <td class="{{ $tdClass }} text-gray-500">
                                {{ $driver->phone }}
                            </td>

                            <td class="{{ $numClass }}
                                {{ $driver->orders > 0 ? 'text-gray-900 dark:text-white font-semibold' : 'text-gray-400' }}">
                                {{ $driver->orders > 0 ? number_format($driver->orders) : '—' }}
                            </td>

                            <td class="{{ $numClass }}
                                {{ $driver->ship > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-400' }}">
                                {{ $driver->ship > 0 ? number_format($driver->ship) . '₫' : '—' }}
                            </td>

                            <td class="{{ $numClass }}
                                {{ $driver->bonus > 0 ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400' }}">
                                {{ $driver->bonus > 0 ? number_format($driver->bonus) . '₫' : '—' }}
                            </td>

                            <td class="{{ $numClass }}
                                {{ $driver->total > 0 ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-gray-400' }}">
                                {{ $driver->total > 0 ? number_format($driver->total) . '₫' : '—' }}
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                                Không có dữ liệu trong kỳ này
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                {{-- Footer totals --}}
                @if ($drivers->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/50 font-semibold">
                            <td colspan="3" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                Trang này ({{ $drivers->count() }} tài xế)
                            </td>
                            <td class="{{ $numClass }} text-gray-900 dark:text-white">
                                {{ number_format($drivers->sum('orders')) }}
                            </td>
                            <td class="{{ $numClass }} text-emerald-600 dark:text-emerald-400">
                                {{ number_format($drivers->sum('ship')) }}₫
                            </td>
                            <td class="{{ $numClass }} text-violet-600 dark:text-violet-400">
                                {{ number_format($drivers->sum('bonus')) }}₫
                            </td>
                            <td class="{{ $numClass }} text-amber-600 dark:text-amber-400">
                                {{ number_format($drivers->sum('total')) }}₫
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- Pagination --}}
        @if ($drivers->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800">
                {{ $drivers->links() }}
            </div>
        @endif
    </div>

</x-filament-panels::page>
