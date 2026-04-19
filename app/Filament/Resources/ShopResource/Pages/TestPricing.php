<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use App\Services\PricingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class TestPricing extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ShopResource::class;

    protected static string $view = 'filament.resources.shop-resource.pages.pricing-tool';

    protected static ?string $title = 'Công cụ Test Giá AI';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('city_id')
                    ->label('Khu vực (HUB)')
                    ->options(\App\Models\City::pluck('name', 'id'))
                    ->required()
                    ->default(\App\Models\City::first()?->id),
                Select::make('service_type')
                    ->label('Loại dịch vụ')
                    ->options([
                        'delivery' => 'Giao hàng nội ô',
                        'bike' => 'Xe ôm công nghệ',
                        'topup' => 'Nạp/Rút tiền',
                        'motor' => 'Lái hộ (Xe máy)',
                        'car' => 'Lái hộ (Ô tô)',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('pickup_address')
                    ->label('Điểm lấy/đón')
                    ->placeholder('Ví dụ: Bến xe Cần Thơ')
                    ->required()
                    ->hidden(fn($get) => $get('service_type') === 'topup'),
                TextInput::make('delivery_address')
                    ->label('Điểm giao/đến')
                    ->placeholder('Ví dụ: Chợ Ninh Kiều')
                    ->required()
                    ->hidden(fn($get) => $get('service_type') === 'topup'),
                TextInput::make('amount')
                    ->label('Số tiền (VNĐ)')
                    ->numeric()
                    ->placeholder('Ví dụ: 12000000')
                    ->visible(fn($get) => $get('service_type') === 'topup'),
            ])
            ->statePath('data');
    }

    public ?float $resultDistance = 0;
    public ?float $resultFee = 0;

    public function calculate()
    {
        $formData = $this->form->getState();
        $pricingService = app(PricingService::class);

        if ($formData['service_type'] === 'topup') {
            $this->resultDistance = 0;
            $this->resultFee = $pricingService->calculate(
                $formData['service_type'],
                $formData['city_id'],
                0,
                (float) ($formData['amount'] ?? 0)
            );
        } else {
            $this->resultDistance = $pricingService->getDistance(
                $formData['pickup_address'],
                $formData['delivery_address']
            );

            if ($this->resultDistance > 0) {
                $this->resultFee = $pricingService->calculate(
                    $formData['service_type'],
                    $formData['city_id'],
                    $this->resultDistance
                );
            } else {
                Notification::make()
                    ->title('Lỗi định vị')
                    ->body('Không thể tìm thấy tọa độ của hai địa chỉ trên Google Maps.')
                    ->danger()
                    ->send();
            }
        }
    }
}
