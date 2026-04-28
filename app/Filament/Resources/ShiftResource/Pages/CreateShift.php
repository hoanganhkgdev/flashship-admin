<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreateShift extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ShiftResource::class;

    public function getTitle(): string
    {
        return 'Thêm ca làm việc';
    }

    public function getSubheading(): ?string
    {
        return 'Thiết lập ca làm việc cho tài xế gói cước tuần theo khu vực.';
    }

    // =========================================================================
    // WIZARD STEPS
    // =========================================================================

    protected function getSteps(): array
    {
        return [

            // ── Bước 1: Thông tin cơ bản ──────────────────────────────────
            Step::make('Thông tin ca')
                ->description('Tên, mã ca và khu vực áp dụng')
                ->icon('heroicon-o-identification')
                ->schema([
                    Forms\Components\Select::make('city_id')
                        ->label('Khu vực áp dụng')
                        ->relationship('city', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Để trống nếu ca dùng chung cho tất cả khu vực.'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên ca')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('VD: Ca sáng, Ca chiều, Ca tối...'),

                    Forms\Components\TextInput::make('code')
                        ->label('Mã ca')
                        ->required()
                        ->unique('shifts', 'code')
                        ->maxLength(50)
                        ->placeholder('VD: morning, evening, full...')
                        ->helperText('Dùng "full" để đánh dấu ca cả ngày — tài xế sẽ bị tính phí Full-time.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Kích hoạt ngay')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Ca không kích hoạt sẽ không hiển thị trong app tài xế.'),
                ])->columns(2),

            // ── Bước 2: Khung giờ ─────────────────────────────────────────
            Step::make('Khung giờ')
                ->description('Giờ bắt đầu và kết thúc ca')
                ->icon('heroicon-o-clock')
                ->schema([
                    Forms\Components\TimePicker::make('start_time')
                        ->label('Giờ bắt đầu')
                        ->required()
                        ->seconds(false)
                        ->live(),

                    Forms\Components\TimePicker::make('end_time')
                        ->label('Giờ kết thúc')
                        ->required()
                        ->seconds(false)
                        ->live(),

                    Forms\Components\Placeholder::make('overnight_notice')
                        ->label('')
                        ->content(fn(Forms\Get $get) => static::overnightHint($get('start_time'), $get('end_time')))
                        ->visible(fn(Forms\Get $get) => static::isOvernightVisible($get('start_time'), $get('end_time')))
                        ->columnSpanFull(),
                ])->columns(2),
        ];
    }

    private static function overnightHint(?string $start, ?string $end): string
    {
        if (!$start || !$end) return '';

        if ($end < $start) {
            return "Ca qua đêm — hệ thống tự động xử lý: tài xế được online từ {$start} hôm nay đến {$end} sáng hôm sau.";
        }

        $startH = (int) explode(':', $start)[0];
        $endH   = (int) explode(':', $end)[0];

        if (($endH - $startH) >= 10) {
            return "Ca dài " . ($endH - $startH) . " tiếng. Nếu muốn tính phí Full-time, đặt mã ca là \"full\".";
        }

        return '';
    }

    private static function isOvernightVisible(?string $start, ?string $end): bool
    {
        if (!$start || !$end) return false;
        return static::overnightHint($start, $end) !== '';
    }

    // =========================================================================
    // HOOKS
    // =========================================================================

    protected function getCreatedNotification(): ?Notification
    {
        $location = $this->record->city ? $this->record->city->name : 'dùng chung';

        return Notification::make()
            ->title("Đã tạo ca {$this->record->name}")
            ->body("Khung giờ: {$this->record->time_range} — Khu vực: {$location}")
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
