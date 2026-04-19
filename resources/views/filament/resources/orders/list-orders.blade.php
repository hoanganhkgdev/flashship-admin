<x-filament::page>
    {{-- Tabs dịch vụ --}}
    <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-6">
            @foreach ([
                'delivery' => '🚚 Giao Đơn',
                'shopping' => '🛍️ Mua Hộ',
                'topup' => '💰 Nạp Tiền',
                'bike' => '🏍️ Xe Ôm',
                'motor' => '🛵 Lái Xe Máy',
                'car' => '🚗 Lái Xe Ô Tô',
            ] as $key => $label)
                <button wire:click="$set('activeTab', '{{ $key }}')"
                    class="px-3 py-2 text-sm font-medium 
                        {{ $activeTab === $key 
                            ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' 
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Table --}}
    {{ $this->table }}
</x-filament::page>
