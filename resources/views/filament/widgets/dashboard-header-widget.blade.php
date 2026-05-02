<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="relative p-6 md:p-8 flex items-center overflow-hidden min-h-[160px]"
            style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">

            <div class="absolute inset-0 opacity-20"
                style="background-image: radial-gradient(at 0% 0%, rgba(255,255,255,0.5) 0, transparent 50%), radial-gradient(at 100% 100%, rgba(0,0,0,0.3) 0, transparent 50%);">
            </div>

            <div class="relative z-10 w-full flex flex-col md:flex-row items-center justify-between gap-6">

                {{-- Greeting --}}
                <div class="flex flex-col gap-1 text-center md:text-left text-white">
                    <div class="flex items-center justify-center md:justify-start gap-2 mb-1">
                        <x-filament::badge color="info" size="xs"
                            class="bg-white/20 text-white border-none backdrop-blur-sm">
                            Flash Ship
                        </x-filament::badge>
                        <span class="text-xs font-semibold opacity-70 italic">Operational Control Center</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
                        {{ now()->hour < 12 ? 'Chào buổi sáng' : (now()->hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối') }},
                        <span class="text-amber-300">{{ explode(' ', auth()->user()->name)[0] }}</span>!
                    </h1>
                    @php
                        $days = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
                        $dateStr = $days[now()->dayOfWeek] . ', ngày ' . now()->format('d') . ' tháng ' . now()->format('m') . ' năm ' . now()->format('Y');
                    @endphp
                    <p class="text-sm opacity-80 font-medium">{{ $dateStr }}</p>
                    <div class="flex items-center justify-center md:justify-start gap-1.5 mt-1">
                        <x-heroicon-o-map-pin class="w-3.5 h-3.5 opacity-70" />
                        <span class="text-sm font-semibold text-amber-200">{{ $cityName }}</span>
                    </div>
                </div>

                {{-- Live metrics --}}
                <div class="flex items-center gap-4">
                    <div class="px-4 py-3 rounded-xl bg-white/10 backdrop-blur-md border border-white/10 flex flex-col items-end min-w-[120px]">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-white/60 mb-1">Hệ thống</span>
                        <span class="text-xl font-bold text-white font-mono tabular-nums leading-none">
                            {{ now()->format('H:i') }}
                        </span>
                        <span class="text-[10px] text-white/50 mt-0.5">{{ now()->format('d/m/Y') }}</span>
                    </div>

                    <div class="px-4 py-3 rounded-xl bg-white/10 backdrop-blur-md border border-white/10 flex flex-col items-end min-w-[120px]">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-white/60 mb-1">Shipper Online</span>
                        <div class="flex items-center gap-2">
                            <span class="flex h-2 w-2 rounded-full bg-green-400 animate-pulse"></span>
                            <span class="text-xl font-bold text-white font-mono tabular-nums leading-none">
                                {{ $onlineDriversCount }}
                            </span>
                        </div>
                        <span class="text-[10px] text-white/50 mt-0.5">{{ $cityName }}</span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-filament-widgets::widget>
