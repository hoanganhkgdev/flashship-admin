<?php

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Resources\CityResource;
use App\Services\GeocodingService;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Str;

class CreateCity extends CreateRecord
{
    use HasWizard;

    protected static string $resource = CityResource::class;

    public function getTitle(): string
    {
        return 'Thêm khu vực mới';
    }

    public function getSubheading(): ?string
    {
        return 'Thiết lập thông tin khu vực vận hành và toạ độ trung tâm HUB.';
    }

    // =========================================================================
    // WIZARD STEPS
    // =========================================================================

    protected function getSteps(): array
    {
        return [
            Step::make('Thông tin chung')
                ->description('Tên, mã vùng và trạng thái hoạt động')
                ->icon('heroicon-o-building-office')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Tên khu vực')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('VD: Kiên Giang, Cần Thơ...')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (empty($state)) return;
                                $code = collect(explode(' ', $state))
                                    ->filter()
                                    ->map(fn($w) => mb_substr($w, 0, 1))
                                    ->join('');
                                $set('code', Str::upper(Str::ascii($code)));
                            }),

                        Forms\Components\TextInput::make('code')
                            ->label('Mã vùng')
                            ->maxLength(50)
                            ->placeholder('VD: KG, CT, HCM...')
                            ->helperText('Tự động tạo từ tên — có thể chỉnh lại.'),
                    ]),

                    Forms\Components\Toggle::make('status')
                        ->label('Kích hoạt ngay sau khi tạo')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Tài xế chỉ thấy khu vực đang kích hoạt.'),
                ]),

            Step::make('Toạ độ HUB')
                ->description('Xác định vị trí trung tâm khu vực')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Section::make()
                        ->description('Chọn địa điểm để tự động điền toạ độ, hoặc nhập thủ công.')
                        ->schema([
                            Forms\Components\Select::make('search_address')
                                ->label('Tìm địa chỉ trung tâm')
                                ->placeholder('Bắt đầu nhập tên địa điểm...')
                                ->searchable()
                                ->getSearchResultsUsing(fn(string $search) => GeocodingService::autocomplete($search))
                                ->reactive()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if (empty($state)) return;
                                    $location = GeocodingService::geocodeAddress($state);
                                    if ($location) {
                                        $set('latitude', $location['lat']);
                                        $set('longitude', $location['lng']);
                                        Notification::make()
                                            ->title('Đã định vị toạ độ!')
                                            ->body('Kinh độ và vĩ độ đã được điền tự động.')
                                            ->success()
                                            ->send();
                                    }
                                })
                                ->dehydrated(false),

                            Grid::make(2)->schema([
                                Forms\Components\TextInput::make('latitude')
                                    ->label('Vĩ độ (Latitude)')
                                    ->required()
                                    ->numeric()
                                    ->placeholder('10.0452...'),

                                Forms\Components\TextInput::make('longitude')
                                    ->label('Kinh độ (Longitude)')
                                    ->required()
                                    ->numeric()
                                    ->placeholder('105.7469...'),
                            ]),
                        ]),
                ]),
        ];
    }

    // =========================================================================
    // HOOKS
    // =========================================================================

    protected function getCreatedNotification(): ?Notification
    {
        $name = $this->record->name;
        $code = $this->record->code;

        return Notification::make()
            ->title("Đã tạo khu vực {$name}")
            ->body($code ? "Mã vùng: {$code}" : null)
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
