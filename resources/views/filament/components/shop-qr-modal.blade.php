<div class="p-4 text-center">
    <p class="mb-4 text-sm text-gray-600">Gửi mã QR hoặc đường link này cho chủ Shop. Khi họ nhấn vào và nhấn <b>"Quan
            tâm"</b>, hệ thống sẽ tự động kết nối định danh.</p>

    <div class="flex justify-center mb-4">
        {{-- Sử dụng QR Code API miễn phí --}}
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode('https://zalo.me/' . config('services.zalo.oa_id', 'PLACEHOLDER') . '?tracking_code=' . $shop->id) }}"
            alt="QR Code" class="border-4 border-white shadow-lg rounded-xl">
    </div>

    <div class="p-3 bg-gray-100 rounded-lg">
        <p class="text-xs font-mono break-all text-blue-600">
            https://zalo.me/{{ config('services.zalo.oa_id', 'PLACEHOLDER') }}?tracking_code={{ $shop->id }}
        </p>
    </div>

    <div class="mt-4">
        <button type="button"
            onclick="navigator.clipboard.writeText('https://zalo.me/{{ config('services.zalo.oa_id', 'PLACEHOLDER') }}?tracking_code={{ $shop->id }}'); $wire.dispatch('notify', { message: 'Đã sao chép link!' })"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">
            Sao chép đường link
        </button>
    </div>

    <div class="mt-6 border-t pt-4">
        <p class="text-xs text-gray-500 mb-2 italic">Hoặc có thể nhắn mã số sau để liên kết thủ công:</p>
        <div class="flex items-center justify-center gap-2">
            <span class="px-3 py-1 bg-gray-200 text-gray-800 rounded font-bold text-lg">MS {{ $shop->id }}</span>
        </div>
        <p class="text-[10px] text-gray-400 mt-1">Soạn tin nhắn: <b>MS {{ $shop->id }}</b> gửi cho Flashship</p>
    </div>
</div>