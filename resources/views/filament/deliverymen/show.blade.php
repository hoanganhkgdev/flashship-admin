<div class="space-y-6">
    {{-- Thông tin tài xế --}}
<div class="bg-white rounded-xl shadow p-6">
    <div class="grid grid-cols-4 gap-6 items-start">
        {{-- Ảnh --}}
        <div class="col-span-1 flex justify-center">
            <img src="{{ $record->profile_photo_path 
                ? asset('storage/' . $record->profile_photo_path) 
                : asset('storage/default-avatar.jpg') }}"
                class="w-40 h-40 object-cover rounded-lg shadow">
        </div>

        {{-- Bảng thông tin --}}
        <div class="col-span-3">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="py-3 px-4 font-medium text-gray-600 w-40">Họ và tên</td>
                        <td class="py-3 px-4 font-semibold text-gray-900">{{ $record->name }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-medium text-gray-600">Phone</td>
                        <td class="py-3 px-4 font-semibold text-gray-900">{{ $record->phone }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-medium text-gray-600">Ca làm việc</td>
                        <td class="py-3 px-4">
                            @foreach ($record->shifts as $shift)
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs font-medium">
                                    {{ $shift->name }}
                                </span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-medium text-gray-600">Trạng thái</td>
                        <td class="py-3 px-4">
                            @if($record->status == 1)
                                <span class="text-green-600 font-semibold">Hoạt động</span>
                            @elseif($record->status == 0)
                                <span class="text-yellow-600 font-semibold">Chờ duyệt</span>
                            @else
                                <span class="text-red-600 font-semibold">Bị khóa</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>



    {{-- Box tài chính --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-sm font-medium text-gray-500 mb-4">Tài chính</h3>
        <div class="grid grid-cols-4 gap-6 text-center">
            <div class="bg-gray-50 rounded-lg p-4 shadow-sm">
                <p class="text-xs text-gray-500">Số dư ví</p>
                <p class="text-xl font-bold text-emerald-600">
                    {{ number_format($record->wallet->balance ?? 0, 0, ',', '.') }} đ
                </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 shadow-sm">
                <p class="text-xs text-gray-500">Công nợ tuần</p>
                <p class="text-xl font-bold text-rose-600">
                    {{ number_format(optional($record->debts()->latest()->first())->amount_due ?? 0, 0, ',', '.') }} đ
                </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 shadow-sm">
                <p class="text-xs text-gray-500">Tổng đơn</p>
                <p class="text-xl font-bold text-amber-600">
                    {{ $record->orders()->count() }}
                </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 shadow-sm">
                <p class="text-xs text-gray-500">Thu nhập</p>
                <p class="text-xl font-bold text-blue-600">
                    {{ number_format($record->orders()->where('status','completed')->sum('shipping_fee') ?? 0, 0, ',', '.') }} đ
                </p>
            </div>
        </div>
    </div>
</div>
