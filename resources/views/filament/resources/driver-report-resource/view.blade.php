<div class="max-w-6xl mx-auto py-8 space-y-8">

    {{-- 🧍 Thông tin tài xế --}}
    <div class="bg-white rounded shadow p-6 flex items-center gap-6">
        <img width="120" src="{{ $driver->profile_photo_path 
            ? asset('storage/' . $driver->profile_photo_path) 
            : asset('storage/default-avatar.jpg') }}" 
            class="w-24 h-24 rounded object-cover shadow">
        <div>
            <p class="text-blue-600 text-sm font-bold">{{ $driver->name }}</p>
            <p class="text-gray-500 text-sm">SĐT: {{ $driver->phone }}</p>
            <p class="text-gray-500 text-sm">Khu vực: {{ $driver->city->name ?? 'Không xác định' }}</p>
            <p class="text-gray-500 text-sm">Ca làm việc: cả ngày</p>
            <p class="text-gray-500 text-sm">Trạng thái: đang Online</p>
        </div>
    </div>

    {{-- 📊 Box thống kê --}}
    <div class="bg-white rounded shadow">
        <x-filament::card>
            <p class="text-xs text-gray-500">Đơn hoàn tất</p>
            <p class="text-2xl font-bold text-emerald-600">{{ $totalCompleted }}</p>
        </x-filament::card>

        <x-filament::card>
            <p class="text-xs text-gray-500">Đơn hủy</p>
            <p class="text-2xl font-bold text-rose-600">{{ $totalCancelled }}</p>
        </x-filament::card>

        <x-filament::card>
            <p class="text-xs text-gray-500">Đang giao</p>
            <p class="text-2xl font-bold text-amber-500">{{ $totalOngoing }}</p>
        </x-filament::card>

        <x-filament::card>
            <p class="text-xs text-gray-500">Tổng doanh thu</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format($totalRevenue) }} đ</p>
        </x-filament::card>

        <x-filament::card>
            <p class="text-xs text-gray-500">Thu nhập 7 ngày</p>
            <p class="text-2xl font-bold text-indigo-600">{{ number_format($incomeWeek) }} đ</p>
        </x-filament::card>
    </div>

    {{-- ⏱️ Thời gian hoạt động trung bình --}}
    <x-filament::card>
        <p class="text-sm text-gray-500">Thời gian hoạt động trung bình / ngày (7 ngày gần nhất)</p>
        <p class="text-2xl font-bold mt-2 text-sky-600">{{ $avgActiveHours }} giờ</p>
    </x-filament::card>

    {{-- 🧾 Đơn gần đây --}}
    <x-filament::card>
        <h3 class="text-sm font-medium text-gray-500 mb-4">Đơn gần đây</h3>
        <table class="w-full text-sm border-collapse">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2 text-left">Mã đơn</th>
                    <th class="px-4 py-2 text-left">Khách hàng</th>
                    <th class="px-4 py-2 text-left">Ngày tạo</th>
                    <th class="px-4 py-2 text-right">Phí ship</th>
                    <th class="px-4 py-2 text-center">Trạng thái</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($recentOrders as $order)
                    <tr>
                        <td class="px-4 py-2 text-blue-600 font-medium">#{{ $order->id }}</td>
                        <td class="px-4 py-2">{{ $order->customer_name ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($order->shipping_fee) }} đ</td>
                        <td class="px-4 py-2 text-center capitalize">{{ $order->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::card>

</div>