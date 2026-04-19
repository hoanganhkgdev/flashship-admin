<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Bộ lọc chọn tài xế -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            {{ $this->form }}
        </div>

        @if($this->data['driverId'] ?? null)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Cột: Đơn đang chờ (Mới tạo) -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold flex items-center gap-2">
                            <x-heroicon-o-megaphone class="w-5 h-5 text-warning-500" />
                            Đơn đang chờ phát (Pending)
                        </h3>
                        <p class="text-sm text-gray-500">Các đơn hàng trong khu vực tài xế đang trực thuộc.</p>

                        <div class="space-y-4">
                            @forelse($this->getAvailableOrders() as $order)
                                <div
                                    class="p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 shadow-sm hover:border-primary-500 transition-colors">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <div class="text-xs text-gray-400">#{{ $order->id }} -
                                                {{ $order->created_at->diffForHumans() }}
                                            </div>
                                            <div class="font-bold text-primary-600">{{ $order->service_type }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-success-600 text-lg">
                                                {{ number_format($order->shipping_fee) }}đ
                                            </div>
                                            <div class="text-xs text-gray-400">{{ $order->distance }} km</div>
                                        </div>
                                    </div>

                                    <div class="space-y-2 mb-4">
                                        <div class="text-sm flex gap-2">
                                            <x-heroicon-o-map-pin class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                            <span><strong>Lấy:</strong> {{ $order->pickup_address }}</span>
                                        </div>
                                        <div class="text-sm flex gap-2">
                                            <x-heroicon-o-flag class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                            <span><strong>Giao:</strong> {{ $order->delivery_address }}</span>
                                        </div>
                                        <div class="text-sm italic text-gray-500">"{{ $order->order_note }}"</div>
                                    </div>

                                    <x-filament::button wire:click="acceptOrder({{ $order->id }})" color="success"
                                        icon="heroicon-o-check" class="w-full">
                                        NHẬN ĐƠN NGAY
                                    </x-filament::button>
                                </div>
                            @empty
                                <div
                                    class="p-8 text-center text-gray-400 border-2 border-dashed border-gray-100 dark:border-gray-800 rounded-xl">
                                    Hiện không có đơn hàng nào đang chờ.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Cột: Đơn của tôi -->
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold flex items-center gap-2">
                            <x-heroicon-o-shopping-bag class="w-5 h-5 text-success-500" />
                            Đơn của tôi (My Orders)
                        </h3>
                        <x-filament::button wire:click="clearMyOrders" color="gray" size="xs" variant="link">Xóa tất
                            cả</x-filament::button>
                    </div>
                    <p class="text-sm text-gray-500">Các đơn bạn đã bấm nhận và đang đi giao.</p>

                    <div class="space-y-4">
                        @forelse($this->getMyOrders() as $order)
                            <div
                                class="p-4 bg-primary-50 dark:bg-primary-900/10 rounded-lg border border-primary-100 dark:border-primary-800 shadow-sm">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <div class="text-xs text-primary-400">#{{ $order->id }} - {{ $order->status }}</div>
                                        <div class="font-bold text-primary-700">{{ $order->service_type }}</div>
                                    </div>
                                    <div class="font-bold text-success-600">{{ number_format($order->shipping_fee) }}đ</div>
                                </div>

                                <div class="space-y-2 mb-4">
                                    <div class="text-sm"><strong>Giao:</strong> {{ $order->delivery_address }}</div>
                                    <div class="text-sm"><strong>SĐT:</strong> {{ $order->delivery_phone }}</div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <x-filament::button wire:click="completeOrder({{ $order->id }})" color="success" size="sm">
                                        HOÀN TẤT
                                    </x-filament::button>
                                    <x-filament::button wire:click="cancelOrder({{ $order->id }})" color="danger" size="sm"
                                        variant="outline">
                                        HỦY ĐƠN
                                    </x-filament::button>
                                </div>
                            </div>
                        @empty
                            <div
                                class="p-8 text-center text-gray-400 border-2 border-dashed border-gray-100 dark:border-gray-800 rounded-xl">
                                Bạn chưa nhận đơn nào.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @else
        <div class="p-12 text-center text-gray-400 border-2 border-dashed border-gray-100 dark:border-gray-800 rounded-2xl">
            <x-heroicon-o-user-circle class="w-16 h-16 mx-auto mb-4 opacity-20" />
            <p>Hãy chọn một tài xế ở bên trên để bắt đầu giả lập luồng nhận đơn.</p>
        </div>
    @endif
    </div>
</x-filament-panels::page>