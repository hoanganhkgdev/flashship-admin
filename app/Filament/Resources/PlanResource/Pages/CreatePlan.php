<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreatePlan extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PlanResource::class;

    public function getTitle(): string
    {
        return 'Thêm gói cước mới';
    }

    public function getSubheading(): ?string
    {
        return 'Thiết lập gói cước cho tài xế theo khu vực và loại hình thu phí.';
    }

    // =========================================================================
    // WIZARD STEPS
    // =========================================================================

    protected function getSteps(): array
    {
        return [

            // ── Bước 1: Chọn loại gói ────────────────────────────────────
            Step::make('Loại gói cước')
                ->description('Chọn hình thức thu phí phù hợp')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Forms\Components\Radio::make('type')
                        ->label('')
                        ->options([
                            Plan::TYPE_WEEKLY     => 'Cước tuần',
                            Plan::TYPE_COMMISSION => 'Chiết khấu %',
                            Plan::TYPE_PARTNER    => 'Tài xế đối tác',
                            Plan::TYPE_FREE       => 'Miễn phí',
                        ])
                        ->descriptions([
                            Plan::TYPE_WEEKLY     => 'Tài xế đăng ký ca cụ thể. Full-time 420k/tuần, part-time 300k/tuần. Chỉ được online trong giờ ca đã đăng ký.',
                            Plan::TYPE_COMMISSION => 'Tài xế chạy tự do, không chia ca. Hệ thống trừ % hoa hồng cố định trên mỗi đơn hoàn thành.',
                            Plan::TYPE_PARTNER    => 'Tài xế đối tác. Không có phí cố định — mức phí do quản lý thoả thuận và ghi trực tiếp trên hồ sơ từng tài xế.',
                            Plan::TYPE_FREE       => 'Dành cho tổng đài, quản lý và admin. Không thu phí, không chia ca, không chiết khấu.',
                        ])
                        ->required()
                        ->live()
                        ->default(Plan::TYPE_WEEKLY),
                ]),

            // ── Bước 2: Thông tin cơ bản ─────────────────────────────────
            Step::make('Thông tin cơ bản')
                ->description('Tên, khu vực và trạng thái')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Tên gói cước')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('VD: Gói tuần Kiên Giang, Chiết khấu 15% Cần Thơ...'),

                    Forms\Components\Select::make('city_id')
                        ->label('Khu vực áp dụng')
                        ->relationship('city', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Để trống nếu gói áp dụng cho tất cả khu vực.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Kích hoạt ngay')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Chỉ có 1 gói active mỗi khu vực được áp dụng tự động khi tài xế đăng ký.'),
                ])->columns(2),

            // ── Bước 3: Cấu hình phí ─────────────────────────────────────
            Step::make('Cấu hình phí')
                ->description('Thiết lập mức phí theo loại đã chọn')
                ->icon('heroicon-o-currency-dollar')
                ->schema([

                    // Cước tuần
                    Forms\Components\Section::make('Phí theo ca làm việc')
                        ->description('Tài xế đăng ký ca sẽ bị trừ phí vào đầu tuần.')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('weekly_fee_full')
                                    ->label('Phí Full-time (tất cả ca)')
                                    ->numeric()
                                    ->prefix('VNĐ')
                                    ->default(420000)
                                    ->required()
                                    ->helperText('Mặc định: 420,000đ/tuần'),

                                Forms\Components\TextInput::make('weekly_fee_part')
                                    ->label('Phí Part-time (1 ca)')
                                    ->numeric()
                                    ->prefix('VNĐ')
                                    ->default(300000)
                                    ->required()
                                    ->helperText('Mặc định: 300,000đ/tuần'),
                            ]),
                        ])
                        ->visible(fn(Forms\Get $get) => $get('type') === Plan::TYPE_WEEKLY),

                    // Chiết khấu %
                    Forms\Components\Section::make('Tỷ lệ chiết khấu')
                        ->description('Hệ thống sẽ trừ % này từ doanh thu mỗi đơn hoàn thành.')
                        ->schema([
                            Forms\Components\TextInput::make('commission_rate')
                                ->label('% Chiết khấu theo đơn')
                                ->numeric()
                                ->suffix('%')
                                ->minValue(0)
                                ->maxValue(100)
                                ->required()
                                ->helperText('VD: 15 = trừ 15% trên mỗi đơn'),
                        ])
                        ->visible(fn(Forms\Get $get) => $get('type') === Plan::TYPE_COMMISSION),

                    // Tài xế đối tác — không cần cấu hình phí
                    Forms\Components\Section::make('Gói tài xế đối tác')
                        ->schema([
                            Forms\Components\Placeholder::make('partner_info')
                                ->label('')
                                ->content('Gói này không có phí cố định. Mức phí do quản lý thoả thuận và ghi trực tiếp trên hồ sơ từng tài xế.'),
                        ])
                        ->visible(fn(Forms\Get $get) => $get('type') === Plan::TYPE_PARTNER),

                    // Miễn phí — tổng đài, quản lý, admin
                    Forms\Components\Section::make('Gói miễn phí')
                        ->schema([
                            Forms\Components\Placeholder::make('free_info')
                                ->label('')
                                ->content('Gói này không thu phí, không chia ca và không có chiết khấu. Dành cho tổng đài, quản lý và admin.'),
                        ])
                        ->visible(fn(Forms\Get $get) => $get('type') === Plan::TYPE_FREE),

                    Forms\Components\Textarea::make('description')
                        ->label('Ghi chú nội bộ')
                        ->rows(2)
                        ->nullable()
                        ->placeholder('Ghi chú thêm về gói cước này...'),
                ]),
        ];
    }

    // =========================================================================
    // HOOKS
    // =========================================================================

    protected function getCreatedNotification(): ?Notification
    {
        $type = match ($this->record->type) {
            Plan::TYPE_WEEKLY     => 'cước tuần',
            Plan::TYPE_COMMISSION => 'chiết khấu %',
            Plan::TYPE_PARTNER    => 'tài xế đối tác',
            Plan::TYPE_FREE       => 'miễn phí',
            default               => $this->record->type,
        };

        return Notification::make()
            ->title("Đã tạo gói {$this->record->name}")
            ->body("Loại: {$type}" . ($this->record->city ? " — {$this->record->city->name}" : ''))
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
