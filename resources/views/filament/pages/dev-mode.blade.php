<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Info --}}
        <div class="p-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-xl text-white">
            <h2 class="text-2xl font-bold mb-2 flex items-center gap-2">
                <x-heroicon-o-beaker class="w-8 h-8" />
                Laboratory & Testing Suite
            </h2>
            <p class="opacity-90">Chào mừng bạn đến với môi trường thử nghiệm FlashShip. Tại đây bạn có thể mô phỏng các
                kịch bản vận hành mà không cần dữ liệu thực tế.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Mock Data Section --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-cube-transparent class="w-5 h-5 text-primary-500" />
                        Dữ liệu mẫu (Mock Data)
                    </div>
                </x-slot>

                <div class="space-y-4">
                    <p class="text-sm text-gray-500">Tạo nhanh các record để kiểm tra giao diện và tính toán.</p>

                    <div class="flex flex-wrap gap-3">
                        <x-filament::button wire:click="createMockOrders(5)" icon="heroicon-m-shopping-cart"
                            color="success">
                            Tạo 5 Đơn hàng mới
                        </x-filament::button>

                        <x-filament::button wire:click="createMockDriver" icon="heroicon-m-user-plus" color="info">
                            Tạo 1 Tài xế Test
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            {{-- Wallet Simulation --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-warning-500" />
                        Giả lập Ví & Tài chính
                    </div>
                </x-slot>

                <div class="space-y-4">
                    <p class="text-sm text-gray-500">Nạp tiền nhanh vào ví tài xế để test luồng rút tiền và đối soát.
                    </p>

                    <x-filament::button onclick="Livewire.dispatch('open-modal', { id: 'add-money-modal' })"
                        icon="heroicon-m-currency-dollar" color="warning" outlined>
                        Nạp tiền Test vào Ví
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- Hazardous Zone --}}
            <x-filament::section class="border-red-100 shadow-sm border-2">
                <x-slot name="heading">
                    <div class="flex items-center gap-2 text-red-600">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                        Vùng Nguy Hiểm (Danger Zone)
                    </div>
                </x-slot>

                <div class="space-y-4">
                    <p class="text-sm text-red-500 font-medium">Cảnh báo: Các thao tác này không thể hoàn tác!</p>

                    <x-filament::button wire:click="deleteAllOrders"
                        wire:confirm="Bạn có chắc chắn muốn XÓA SẠCH toàn bộ đơn hàng không?" icon="heroicon-m-trash"
                        color="danger">
                        Xóa Sạch Lịch Sử Đơn Hàng
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- System Health --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-shield-check class="w-5 h-5 text-success-500" />
                        Trạng thái Hệ thống
                    </div>
                </x-slot>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <span class="text-xs text-gray-400 uppercase font-bold">Phiên bản</span>
                        <p class="font-mono font-bold text-gray-700">FlashShip v2.0-Alpha</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <span class="text-xs text-gray-400 uppercase font-bold">Môi trường</span>
                        <p class="font-mono font-bold text-green-600 uppercase">{{ config('app.env') }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>

    {{-- Modals --}}
    <x-filament::modal id="add-money-modal" width="md" heading="Nạp tiền Test vào Ví">
        <form wire:submit="submitAddMoney">
            <div class="space-y-4 p-4 text-left">
                <p class="text-sm text-gray-500 italic mb-4">Chọn một tài xế bất kỳ để nạp nhanh số dư phục vụ kiểm thử.</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium block">Chọn tài xế</label>
                        <select wire:model="driver_id" class="w-full mt-1 border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 text-gray-700">
                            <option value="">-- Chọn tài xế --</option>
                            @foreach(App\Models\User::role('driver')->get() as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->name }} ({{ $driver->phone }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium block">Số tiền muốn nạp</label>
                        <input type="number" wire:model="amount" class="w-full mt-1 border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 text-gray-700" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-filament::button color="gray" x-on:click="close" type="button">Hủy</x-filament::button>
                    <x-filament::button color="primary" type="submit">Thực hiện nạp</x-filament::button>
                </div>
            </div>
        </form>
    </x-filament::modal>
</x-filament-panels::page>