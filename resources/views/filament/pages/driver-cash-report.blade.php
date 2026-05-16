<x-filament-panels::page>
    @php
        $stats   = $this->getStats();
        $drivers = $this->getData();
        $period  = $this->getPeriodLabel();
        $isAdmin = auth()->user()->hasRole('admin');
        $cities  = $isAdmin ? $this->getCities() : collect();

        $net = $stats['net'];

        $thClass  = 'px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap';
        $thSort   = $thClass . ' cursor-pointer select-none hover:text-gray-900 dark:hover:text-white transition-colors';
        $tdClass  = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-300';
        $numClass = 'px-4 py-3 text-sm tabular-nums text-right';
    @endphp

    {{-- ── Summary bar ─────────────────────────────────────────────── --}}
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">

        <div style="flex:1; min-width:160px;" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng thu · {{ $period }}</p>
            <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                {{ number_format($stats['total_thu']) }}₫
            </p>
            <p class="text-xs text-gray-400">{{ $stats['count_thu_drivers'] }} tài xế đã đóng</p>
        </div>

        <div style="flex:1; min-width:160px;" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Tổng chi · {{ $period }}</p>
            <p class="text-xl font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                {{ number_format($stats['total_chi']) }}₫
            </p>
            <p class="text-xs text-gray-400">{{ $stats['count_chi_drivers'] }} tài xế đã rút</p>
        </div>

        <div style="flex:1; min-width:160px;" class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400">Chênh lệch (thu − chi)</p>
            <p class="text-xl font-bold tabular-nums {{ $net >= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400' }}">
                {{ ($net >= 0 ? '+' : '') . number_format($net) }}₫
            </p>
            <p class="text-xs text-gray-400">{{ $net >= 0 ? 'dương (đang dư)' : 'âm (đang thiếu)' }}</p>
        </div>

    </div>

    {{-- ── Controls ────────────────────────────────────────────────── --}}
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
            <button wire:click="$set('mode','range')"
                class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-200 dark:border-gray-700
                    {{ $mode === 'range'
                        ? 'bg-primary-600 text-white'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Khoảng ngày
            </button>
        </div>

        {{-- Date / Month / Range picker --}}
        @if ($mode === 'month')
            <input type="month" wire:model.live="month"
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                       text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                       focus:outline-none focus:ring-2 focus:ring-primary-500">
        @elseif ($mode === 'range')
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="from"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                           text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                           focus:outline-none focus:ring-2 focus:ring-primary-500">
                <span class="text-sm text-gray-400">–</span>
                <input type="date" wire:model.live="until"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                           text-sm text-gray-700 dark:text-gray-300 px-3 py-2 shadow-sm
                           focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
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

    {{-- ── Table ───────────────────────────────────────────────────── --}}
    <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
                        <th class="{{ $thClass }} w-10 text-center">#</th>

                        <th wire:click="toggleSort('name')" class="{{ $thSort }}">
                            Tài xế
                            @if ($sortBy === 'name')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th class="{{ $thClass }}">SĐT</th>

                        <th wire:click="toggleSort('thu')" class="{{ $thSort }} text-right">
                            <span class="text-emerald-600 dark:text-emerald-400">Thu (đã đóng)</span>
                            @if ($sortBy === 'thu')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th wire:click="toggleSort('chi')" class="{{ $thSort }} text-right">
                            <span class="text-rose-600 dark:text-rose-400">Chi (đã rút)</span>
                            @if ($sortBy === 'chi')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>

                        <th wire:click="toggleSort('net')" class="{{ $thSort }} text-right">
                            Chênh lệch
                            @if ($sortBy === 'net')
                                <span class="ml-0.5">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($drivers as $i => $driver)
                        @php $rowNet = (float) $driver->thu - (float) $driver->chi; @endphp
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

                            <td class="{{ $numClass }} {{ $driver->thu > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-400' }}">
                                {{ $driver->thu > 0 ? number_format($driver->thu) . '₫' : '—' }}
                            </td>

                            <td class="{{ $numClass }} {{ $driver->chi > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-gray-400' }}">
                                {{ $driver->chi > 0 ? number_format($driver->chi) . '₫' : '—' }}
                            </td>

                            <td class="{{ $numClass }} font-bold
                                {{ $rowNet > 0 ? 'text-amber-600 dark:text-amber-400' : ($rowNet < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400') }}">
                                @if ($driver->thu == 0 && $driver->chi == 0)
                                    —
                                @else
                                    {{ ($rowNet >= 0 ? '+' : '') . number_format($rowNet) }}₫
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                                Không có dữ liệu trong kỳ này
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($drivers->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/50 font-semibold">
                            <td colspan="3" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                Trang này ({{ $drivers->count() }} tài xế)
                            </td>
                            <td class="{{ $numClass }} text-emerald-600 dark:text-emerald-400">
                                {{ number_format($drivers->sum('thu')) }}₫
                            </td>
                            <td class="{{ $numClass }} text-rose-600 dark:text-rose-400">
                                {{ number_format($drivers->sum('chi')) }}₫
                            </td>
                            @php $pageNet = (float)$drivers->sum('thu') - (float)$drivers->sum('chi'); @endphp
                            <td class="{{ $numClass }} {{ $pageNet >= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ ($pageNet >= 0 ? '+' : '') . number_format($pageNet) }}₫
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($drivers->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800">
                {{ $drivers->links() }}
            </div>
        @endif
    </div>

    {{-- ── Legend ───────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-4 text-xs text-gray-400 dark:text-gray-500 pt-1">
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <b class="text-gray-600 dark:text-gray-300">Thu:</b> tiền tài xế đã đóng vào hệ thống (driver_debts)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-rose-500"></span>
            <b class="text-gray-600 dark:text-gray-300">Chi:</b> tiền rút đã được duyệt (withdraw_requests approved)
        </span>
    </div>

</x-filament-panels::page>
