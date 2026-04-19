<x-filament-panels::page>
<style>
.tp-wrap { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.tp-card { background: white; border-radius: 20px; border: 1px solid #f1f5f9; box-shadow: 0 4px 20px -4px rgba(0,0,0,0.06); overflow: hidden; }
.tp-head { padding: 16px 24px; font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.08em; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.tp-body { padding: 24px; }

.result-box { text-align: center; padding: 24px; background: linear-gradient(135deg, #eff6ff, #f5f3ff); border-radius: 16px; border: 1px solid #ddd6fe; margin-bottom: 16px; }
.result-label { font-size: 12px; font-weight: 700; color: #6d28d9; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
.result-value { font-size: 36px; font-weight: 900; color: #1e293b; }
.result-sub { font-size: 12px; color: #64748b; margin-top: 4px; }

.result-dist { text-align: center; padding: 16px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0; }
.result-dist-val { font-size: 22px; font-weight: 800; color: #166534; }

.calc-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; border: none; border-radius: 14px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(37,99,235,0.25); }
.calc-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(37,99,235,0.3); }
.calc-btn:active { transform: scale(0.98); }

.empty-result { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 200px; color: #94a3b8; font-size: 13px; }
.empty-result .icon { font-size: 48px; margin-bottom: 12px; }

@media(max-width: 768px) { .tp-wrap { grid-template-columns: 1fr; } }
</style>

<div class="tp-wrap">

    {{-- Form tính giá --}}
    <div class="tp-card">
        <div class="tp-head">🧮 Nhập thông tin</div>
        <div class="tp-body">
            <x-filament-panels::form wire:submit="calculate">
                {{ $this->form }}
                <div style="margin-top: 20px">
                    <button type="submit" class="calc-btn">
                        🔍 Tính giá ngay
                    </button>
                </div>
            </x-filament-panels::form>
        </div>
    </div>

    {{-- Kết quả --}}
    <div class="tp-card">
        <div class="tp-head">💰 Kết quả tính giá</div>
        <div class="tp-body">
            @if($resultFee > 0)
                <div class="result-box">
                    <div class="result-label">Phí dịch vụ</div>
                    <div class="result-value">{{ number_format($resultFee, 0, ',', '.') }}đ</div>
                    <div class="result-sub">Đã bao gồm VAT & phụ phí</div>
                </div>

                @if($resultDistance > 0)
                <div class="result-dist">
                    <div style="font-size: 11px; font-weight: 700; color: #166534; margin-bottom: 4px">📏 Khoảng cách</div>
                    <div class="result-dist-val">{{ number_format($resultDistance, 1) }} km</div>
                </div>
                @endif

                <div style="margin-top: 14px; padding: 12px 16px; background: #f8fafc; border-radius: 12px; font-size: 12.5px; color: #64748b; line-height: 1.6">
                    ℹ️ Đây là giá ước tính theo cấu hình hiện tại. Giá thực tế có thể thay đổi theo từng đơn.
                </div>
            @else
                <div class="empty-result">
                    <div class="icon">🧮</div>
                    <div style="font-weight: 600; color: #475569; margin-bottom: 4px">Chưa có kết quả</div>
                    <div>Nhập thông tin và bấm <strong>Tính giá ngay</strong></div>
                </div>
            @endif
        </div>
    </div>

</div>
</x-filament-panels::page>
