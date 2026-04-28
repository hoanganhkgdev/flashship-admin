@php
    $user = auth()->user();
@endphp

@if($user && $user->hasRole('admin'))
    @php
        $cities = \App\Models\City::active()->orderBy('name')->get(['id', 'name']);
        $current = session('current_city_id') ?? 0;
        $currentCity = $cities->firstWhere('id', $current)?->name ?? 'Toàn quốc';
    @endphp

    <div 
        x-data="{ 
            open: false,
            toggle() { this.open = !this.open },
            close() { this.open = false }
        }" 
        class="relative city-switcher-container"
        x-on:click.away="close()"
    >
        {{-- Nút Dropdown --}}
        <button 
            x-on:click="toggle()"
            type="button"
            class="city-trigger-btn"
            :class="{ 'is-active': open }"
        >
            <div class="trigger-content">
                <div class="loc-icon-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <div class="city-info">
                    <span class="city-label">KHU VỰC</span>
                    <span class="city-name">{{ $currentCity }}</span>
                </div>
            </div>
            <svg class="chevron-icon" :class="{ 'rotate-180': open }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>

        {{-- Panel Dropdown --}}
        <div 
            x-show="open"
            x-transition:enter="enter-anim"
            x-transition:enter-start="enter-start"
            x-transition:enter-end="enter-end"
            x-transition:leave="leave-anim"
            x-cloak
            class="city-dropdown-panel"
        >
            <div class="panel-header">Chọn khu vực làm việc</div>
            <div class="city-list">
                {{-- Toàn quốc --}}
                <a
                    href="{{ route('admin.switch-city', 0) }}"
                    class="city-item {{ $current == 0 ? 'active' : '' }}"
                >
                    <span class="item-bullet"></span>
                    <span class="item-name">Toàn quốc</span>
                    @if($current == 0)
                        <div class="active-indicator">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                    @endif
                </a>

                @foreach ($cities as $city)
                    <a
                        href="{{ route('admin.switch-city', $city->id) }}"
                        class="city-item {{ $current == $city->id ? 'active' : '' }}"
                    >
                        <span class="item-bullet"></span>
                        <span class="item-name">{{ $city->name }}</span>
                        @if($current == $city->id)
                            <div class="active-indicator">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <style>
        .city-switcher-container {
            padding: 0 12px;
            --primary: #3b82f6;
            --primary-bg: rgba(59, 130, 246, 0.1);
        }

        .city-trigger-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            min-width: 220px;
            padding: 10px 16px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .city-trigger-btn:hover {
            border-color: var(--primary);
            background: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }

        .city-trigger-btn.is-active {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-bg);
        }

        .trigger-content {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .loc-icon-wrapper {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-bg);
            color: var(--primary);
            border-radius: 12px;
            flex-shrink: 0;
        }

        .loc-icon-wrapper svg {
            width: 18px;
            height: 18px;
        }

        .city-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 0;
        }

        .city-label {
            font-size: 9px;
            font-weight: 800;
            color: #94a3b8;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            line-height: 1;
            margin-bottom: 2px;
        }

        .city-name {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }

        .chevron-icon {
            width: 16px;
            height: 16px;
            color: #94a3b8;
            transition: transform 0.3s ease;
        }

        .city-dropdown-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
            z-index: 100;
            overflow: hidden;
            transform-origin: top;
        }

        .panel-header {
            padding: 14px 20px;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }

        .city-list {
            max-height: 320px;
            overflow-y: auto;
            padding: 8px;
        }

        .city-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .city-item:hover {
            background: #f8fafc;
        }

        .city-item.active {
            background: var(--primary-bg);
            color: var(--primary);
        }

        .item-bullet {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #cbd5e1;
            transition: all 0.2s ease;
        }

        .city-item.active .item-bullet {
            background: var(--primary);
            box-shadow: 0 0 0 4px rgba(59,130,246,0.2);
        }

        .item-name {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            flex: 1;
        }

        .city-item.active .item-name {
            color: var(--primary);
            font-weight: 700;
        }

        .active-indicator {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .active-indicator svg {
            width: 14px;
            height: 14px;
        }

        /* Animations */
        .enter-anim { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .enter-start { opacity: 0; transform: translateY(-10px) scale(0.95); }
        .enter-end { opacity: 1; transform: translateY(0) scale(1); }
        .leave-anim { transition: opacity 0.2s ease, transform 0.2s ease; }

        [x-cloak] { display: none !important; }

        /* Scrollbar tinh tế */
        .city-list::-webkit-scrollbar { width: 4px; }
        .city-list::-webkit-scrollbar-track { background: transparent; }
        .city-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .city-list::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
@endif