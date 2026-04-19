<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        // Sau khi tạo đơn → quay về danh sách đơn hàng
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['shipping_fee'] = static::normalizeFee($data['shipping_fee'] ?? 0);
        $data['bonus_fee']    = static::normalizeFee($data['bonus_fee'] ?? 0);

        if (!empty($data['delivery_man_id'])) {
            $data['status'] = 'assigned';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $order = $this->record;

        if ($order->delivery_man_id) {
            $driver = \App\Models\User::find($order->delivery_man_id);
            if ($driver) {
                \App\Services\NotificationService::notifyOrderAssigned($order, $driver);
            }
        }
    }

    /**
     * Chuẩn hóa giá trị phí:
     *   "15"     → 15000
     *   "15k"    → 15000
     *   "15.5k"  → 15500
     *   "15000"  → 15000
     *   "15tr"   → 15000000  (ít dùng nhưng có)
     */
    public static function normalizeFee(mixed $value): int
    {
        $raw = trim((string) $value);

        if ($raw === '' || $raw === '0') return 0;

        $lower = mb_strtolower($raw);

        // Có suffix "tr" / "triệu"
        if (preg_match('/^([\d.,]+)\s*(tr|triệu)/u', $lower, $m)) {
            return (int) round(str_replace(',', '.', $m[1]) * 1_000_000);
        }

        // Có suffix "k" / "nghìn" / "ngàn"
        if (preg_match('/^([\d.,]+)\s*(k|nghìn|ngàn)/u', $lower, $m)) {
            return (int) round(str_replace(',', '.', $m[1]) * 1_000);
        }

        // Chỉ là số thuần
        $num = (float) str_replace([',', ' '], ['.', ''], $raw);

        // Nếu < 1000 → coi là đơn vị nghìn (vd: 15 → 15k = 15000)
        if ($num > 0 && $num < 1000) {
            return (int) round($num * 1_000);
        }

        return (int) round($num);
    }
}

