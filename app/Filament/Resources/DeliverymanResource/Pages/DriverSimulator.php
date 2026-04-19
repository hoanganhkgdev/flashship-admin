<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use App\Models\User;
use App\Models\Order;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class DriverSimulator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = DeliverymanResource::class;

    protected static string $view = 'filament.resources.deliveryman-resource.pages.driver-simulator';

    protected static ?string $title = 'Giả lập Tài xế';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('driverId')
                    ->label('Chọn tài xế để đóng vai')
                    ->options(User::role('driver')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->placeholder('Chọn một tài xế để bắt đầu'),
            ])
            ->statePath('data');
    }

    public function acceptOrder(int $orderId)
    {
        $driverId = $this->data['driverId'] ?? null;
        if (!$driverId) {
            Notification::make()->title('Vui lòng chọn tài xế trước!')->danger()->send();
            return;
        }

        $driver = User::find($driverId);

        // Cập nhật atomically để tránh tranh chấp
        $affected = DB::table('orders')
            ->where('id', $orderId)
            ->where('status', 'pending')
            ->update([
                'status' => 'assigned',
                'delivery_man_id' => $driver->id,
                'updated_at' => now(),
            ]);

        if ($affected) {
            Notification::make()->title('Đã nhận đơn thành công!')->success()->send();
        } else {
            Notification::make()->title('Đơn đã bị người khác nhận hoặc không khả dụng!')->danger()->send();
        }
    }

    public function completeOrder(int $orderId)
    {
        $order = Order::find($orderId);
        if (!$order)
            return;

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Notification::make()->title('Đã hoàn thành đơn!')->success()->send();
    }

    public function cancelOrder(int $orderId)
    {
        $order = Order::find($orderId);
        if (!$order)
            return;

        $order->update([
            'status' => 'cancelled',
        ]);

        Notification::make()->title('Đã hủy đơn!')->warning()->send();
    }

    public function clearMyOrders()
    {
        $driverId = $this->data['driverId'] ?? null;
        if (!$driverId)
            return;

        Order::where('delivery_man_id', $driverId)
            ->whereIn('status', ['assigned', 'delivering'])
            ->update(['status' => 'pending', 'delivery_man_id' => null]);

        Notification::make()->title('Đã trả toàn bộ đơn về trạng thái chờ!')->info()->send();
    }

    public function getAvailableOrders()
    {
        $driverId = $this->data['driverId'] ?? null;
        if (!$driverId)
            return collect();

        $driver = User::find($driverId);
        if (!$driver || !$driver->city_id)
            return collect();

        return Order::where('city_id', $driver->city_id)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    public function getMyOrders()
    {
        $driverId = $this->data['driverId'] ?? null;
        if (!$driverId)
            return collect();

        return Order::where('delivery_man_id', $driverId)
            ->whereIn('status', ['assigned', 'delivering'])
            ->latest()
            ->get();
    }
}
