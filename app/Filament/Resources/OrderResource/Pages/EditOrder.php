<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        // Quay lại danh sách đơn sau khi sửa
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['shipping_fee'] = CreateOrder::normalizeFee($data['shipping_fee'] ?? 0);
        $data['bonus_fee']    = CreateOrder::normalizeFee($data['bonus_fee'] ?? 0);

        // Set completed_at khi admin đổi tay status sang completed qua form
        if (($data['status'] ?? '') === 'completed' && $this->record->status !== 'completed') {
            $data['completed_at'] = now();
        }

        // Bảo vệ race condition: tài xế nhận đơn trong lúc tổng đài đang sửa form.
        // Form load khi đơn còn pending (delivery_man_id = null), sau đó tài xế nhận đơn,
        // nếu không check thì lưu form sẽ ghi null lên delivery_man_id → đơn "assigned" nhưng mất tài xế.
        if (empty($data['delivery_man_id'])) {
            $currentDriverId = \App\Models\Order::where('id', $this->record->id)
                ->value('delivery_man_id');

            if ($currentDriverId) {
                $data['delivery_man_id'] = $currentDriverId;

                Notification::make()
                    ->title('⚠️ Đơn vừa có tài xế nhận trong lúc bạn sửa!')
                    ->body('Thông tin tài xế đã được giữ nguyên. Vui lòng kiểm tra lại đơn hàng.')
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }

        return $data;
    }
}

