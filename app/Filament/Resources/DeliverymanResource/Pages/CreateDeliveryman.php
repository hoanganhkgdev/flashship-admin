<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use App\Models\Plan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Builder;

class CreateDeliveryman extends CreateRecord
{
    use HasWizard;

    protected static string $resource = DeliverymanResource::class;

    public function getTitle(): string
    {
        return 'Thêm tài xế mới';
    }

    public function getSubheading(): ?string
    {
        return 'Tạo tài khoản tài xế và phân công khu vực, gói cước.';
    }

    protected function getSteps(): array
    {
        return [
            Step::make('Thông tin cá nhân')
                ->description('Họ tên, liên hệ và thông tin đăng nhập')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\FileUpload::make('profile_photo_path')
                        ->label('Ảnh đại diện')
                        ->image()->avatar()->directory('avatars')
                        ->imageEditor()->columnSpanFull(),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Họ và tên')->required()->placeholder('Nguyễn Văn A'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Số điện thoại')->tel()->required()
                            ->unique(User::class, 'phone')->placeholder('090xxxxxxx'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')->email()->nullable()->placeholder('email@example.com'),

                        Forms\Components\TextInput::make('address')
                            ->label('Địa chỉ')->nullable()->placeholder('Số nhà, đường, quận...'),

                        Forms\Components\TextInput::make('password')
                            ->label('Mật khẩu')->password()->revealable()
                            ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                            ->required()->placeholder('Mật khẩu đăng nhập')
                            ->columnSpanFull(),
                    ]),
                ]),

            Step::make('Phân công vận hành')
                ->description('Khu vực, ca làm việc và trạng thái')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Forms\Components\Select::make('city_id')
                        ->label('Khu vực trực thuộc')
                        ->relationship('city', 'name', fn(Builder $query) => $query->active())
                        ->searchable()->preload()->required()->live()
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('plan_id', null);
                            $set('_plan_type', null);
                            $set('shifts', []);
                        }),

                    Forms\Components\Select::make('plan_id')
                        ->label('Gói cước')
                        ->options(fn(Forms\Get $get) => Plan::active()
                            ->forCity((int) $get('city_id'))
                            ->get()
                            ->mapWithKeys(fn($plan) => [
                                $plan->id => $plan->name . ' — ' . match ($plan->type) {
                                    Plan::TYPE_WEEKLY     => 'Cước tuần',
                                    Plan::TYPE_COMMISSION => 'Chiết khấu %',
                                    Plan::TYPE_PARTNER    => 'Đối tác',
                                    Plan::TYPE_FREE       => 'Miễn phí',
                                    default               => $plan->type,
                                },
                            ])
                            ->toArray()
                        )
                        ->required()->live()->native(false)
                        ->placeholder('Chọn gói cước...')
                        ->helperText('Gói cước đang áp dụng cho khu vực này.')
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            $set('_plan_type', Plan::find($state)?->type);
                            $set('shifts', []);
                        })
                        ->visible(fn(Forms\Get $get) => filled($get('city_id'))),

                    Forms\Components\Hidden::make('_plan_type'),

                    Forms\Components\TextInput::make('custom_commission_rate')
                        ->label('Chiết khấu riêng (%)')->numeric()
                        ->minValue(0)->maxValue(100)->step(0.1)->suffix('%')
                        ->required(fn(Forms\Get $get) => $get('_plan_type') === Plan::TYPE_PARTNER)
                        ->helperText('Tỷ lệ % áp dụng riêng cho tài xế này. Bắt buộc với gói đối tác.')
                        ->visible(fn(Forms\Get $get) => $get('_plan_type') === Plan::TYPE_PARTNER),

                    Forms\Components\Select::make('shifts')
                        ->label('Ca làm việc đăng ký')->multiple()
                        ->relationship(
                            name: 'shifts',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn(Builder $query, Forms\Get $get) => $query->where(
                                fn($q) => $q->where('city_id', $get('city_id'))->orWhereNull('city_id')
                            )
                        )
                        ->preload()
                        ->visible(fn(Forms\Get $get) => $get('_plan_type') === Plan::TYPE_WEEKLY),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái tài khoản')
                        ->options([0 => 'Chờ duyệt', 1 => 'Hoạt động', 2 => 'Bị khóa'])
                        ->default(0)->required()->native(false),
                ]),
        ];
    }

    protected function afterCreate(): void
    {
        $this->record->assignRole('driver');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title("Đã tạo tài khoản {$this->record->name}")
            ->body('Tài xế đã được thêm với trạng thái Chờ duyệt.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
