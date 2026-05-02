<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliverymanResource;
use App\Filament\Resources\DriverLicenseResource\Pages;
use App\Filament\Resources\DriverLicenseResource\Widgets\DriverLicenseOverviewWidget;
use App\Models\DriverLicense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriverLicenseResource extends Resource
{
    protected static ?string $model = DriverLicense::class;

    protected static ?string $navigationIcon   = 'heroicon-o-identification';
    protected static ?string $navigationLabel  = 'Kiểm duyệt Bằng lái';
    protected static ?string $modelLabel       = 'hồ sơ bằng lái';
    protected static ?string $pluralModelLabel = 'Hồ sơ bằng lái';
    protected static ?int    $navigationSort   = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'QUẢN LÝ TÀI XẾ';
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = DriverLicense::pending()->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // =========================================================================
    // FORM
    // =========================================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Section::make('Tài xế & Ảnh giấy tờ')
                    ->description('Chọn tài xế và tải lên ảnh bằng lái')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Tài xế')
                            ->relationship('user', 'name', fn(Builder $q) => $q->role('driver'))
                            ->searchable()->preload()->required()
                            ->disabled(fn(string $context) => $context === 'edit'),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('Ảnh bằng lái xe')
                            ->image()->directory('licenses')->disk('public')
                            ->imageEditor()->columnSpanFull(),
                    ])->columnSpan(2),

                Forms\Components\Section::make('Kết quả kiểm duyệt')
                    ->description('Trạng thái xét duyệt hồ sơ')
                    ->icon('heroicon-o-check-badge')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                DriverLicense::STATUS_PENDING  => 'Chờ duyệt',
                                DriverLicense::STATUS_APPROVED => 'Đã duyệt',
                                DriverLicense::STATUS_REJECTED => 'Từ chối',
                            ])
                            ->required()->native(false),
                    ])->columnSpan(1),
            ]),
        ]);
    }

    // =========================================================================
    // INFOLIST
    // =========================================================================

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ── HERO: DRIVER PROFILE ──────────────────────────────────────────
            Components\Section::make()->schema([
                Components\Split::make([
                    Components\Group::make([
                        Components\ImageEntry::make('user.profile_photo_path')
                            ->label(false)->circular()->size(100)
                            ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->user?->name ?? '?') . '&color=FFFFFF&background=03A9F4'),
                        Components\TextEntry::make('user.name')
                            ->label(false)->weight(FontWeight::Bold)
                            ->size(Components\TextEntry\TextEntrySize::Large),
                        Components\TextEntry::make('user.phone')
                            ->label('Điện thoại')->icon('heroicon-m-phone')->copyable(),
                        Components\TextEntry::make('user.city.name')
                            ->label('Khu vực')->icon('heroicon-m-map-pin'),
                    ])->grow(false),

                    Components\Section::make()->schema([
                        Components\TextEntry::make('status')
                            ->label('Kết quả kiểm duyệt')->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                DriverLicense::STATUS_PENDING  => 'CHỜ DUYỆT',
                                DriverLicense::STATUS_APPROVED => 'ĐÃ DUYỆT',
                                DriverLicense::STATUS_REJECTED => 'TỪ CHỐI',
                                default                        => strtoupper($state),
                            })
                            ->color(fn($state) => match ($state) {
                                DriverLicense::STATUS_PENDING  => 'warning',
                                DriverLicense::STATUS_APPROVED => 'success',
                                DriverLicense::STATUS_REJECTED => 'danger',
                                default                        => 'gray',
                            })
                            ->size(Components\TextEntry\TextEntrySize::Large),
                        Components\TextEntry::make('created_at')
                            ->label('Ngày nộp hồ sơ')->dateTime('H:i d/m/Y'),
                        Components\TextEntry::make('updated_at')
                            ->label('Cập nhật lần cuối')->dateTime('H:i d/m/Y'),
                    ]),
                ])->from('md'),
            ]),

            // ── ẢNH BẰNG LÁI ─────────────────────────────────────────────────
            Components\Section::make('Ảnh bằng lái xe')
                ->icon('heroicon-o-photo')
                ->schema([
                    Components\TextEntry::make('image_display')
                        ->label(false)
                        ->state(fn($record) => '<a href="' . e(asset('storage/' . $record->image_path)) . '" target="_blank" rel="noopener">
                            <img src="' . e(asset('storage/' . $record->image_path)) . '"
                                 style="max-width:100%; max-height:520px; object-fit:contain; border-radius:10px; cursor:zoom-in; display:block;" />
                        </a>')
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // =========================================================================
    // TABLE
    // =========================================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\ImageColumn::make('user.profile_photo_path')
                    ->label('')->circular()->size(44)
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->user?->name ?? '?') . '&background=03A9F4&color=fff'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Tài xế')->searchable()->sortable()->weight('bold')
                    ->description(fn($record) => ($record->user?->phone ?? '—') . ' · ' . ($record->user?->city?->name ?? '—')),

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Ảnh bằng lái')->disk('public')
                    ->size(72)->square()
                    ->url(fn($record) => asset('storage/' . $record->image_path))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')->badge()->alignCenter()
                    ->formatStateUsing(fn($state) => match ($state) {
                        DriverLicense::STATUS_PENDING  => 'Chờ duyệt',
                        DriverLicense::STATUS_APPROVED => 'Đã duyệt',
                        DriverLicense::STATUS_REJECTED => 'Từ chối',
                        default                        => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        DriverLicense::STATUS_PENDING  => 'warning',
                        DriverLicense::STATUS_APPROVED => 'success',
                        DriverLicense::STATUS_REJECTED => 'danger',
                        default                        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày gửi')->since()->color('gray')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        DriverLicense::STATUS_PENDING  => 'Chờ duyệt',
                        DriverLicense::STATUS_APPROVED => 'Đã duyệt',
                        DriverLicense::STATUS_REJECTED => 'Từ chối',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check-badge')->color('success')->size('sm')
                    ->visible(fn($record) => $record->status === DriverLicense::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Duyệt hồ sơ bằng lái')
                    ->modalDescription(fn($record) => "Xác nhận bằng lái xe của {$record->user?->name} hợp lệ?")
                    ->action(function ($record) {
                        $record->update(['status' => DriverLicense::STATUS_APPROVED]);
                        $record->user?->update(['has_car_license' => true]);
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('reject')
                        ->label('Từ chối')
                        ->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn($record) => $record->status === DriverLicense::STATUS_PENDING)
                        ->requiresConfirmation()
                        ->modalHeading('Từ chối hồ sơ')
                        ->modalDescription('Đánh dấu bằng lái không đạt. Tài xế có thể gửi lại hồ sơ mới.')
                        ->action(function ($record) {
                            $record->update(['status' => DriverLicense::STATUS_REJECTED]);
                            $record->user?->update(['has_car_license' => false]);
                        }),

                    Tables\Actions\Action::make('view_driver')
                        ->label('Xem hồ sơ tài xế')
                        ->icon('heroicon-o-user')
                        ->url(fn($record) => DeliverymanResource::getUrl('view', ['record' => $record->user_id]))
                        ->openUrlInNewTab(),

                    Tables\Actions\EditAction::make()->label('Chỉnh sửa'),
                    Tables\Actions\DeleteAction::make()->label('Xóa hồ sơ'),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Duyệt đã chọn')
                        ->icon('heroicon-o-check-badge')->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Duyệt các hồ sơ đã chọn')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['status' => DriverLicense::STATUS_APPROVED]);
                                $record->user?->update(['has_car_license' => true]);
                            });
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =========================================================================
    // PAGES & WIDGETS & PERMISSIONS
    // =========================================================================

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDriverLicenses::route('/'),
            'create' => Pages\CreateDriverLicense::route('/create'),
            'edit'   => Pages\EditDriverLicense::route('/{record}/edit'),
            'view'   => Pages\ViewDriverLicense::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [DriverLicenseOverviewWidget::class];
    }

    public static function canViewAny(): bool       { return auth()->check() && auth()->user()->hasRole('admin'); }
    public static function canCreate(): bool        { return auth()->check() && auth()->user()->hasRole('admin'); }
    public static function canEdit($record): bool   { return auth()->check() && auth()->user()->hasRole('admin'); }
    public static function canDelete($record): bool { return auth()->check() && auth()->user()->hasRole('admin'); }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['user:id,name,phone,city_id,profile_photo_path,status', 'user.city:id,name']);

        $user = auth()->user();

        if ($user->hasRole('admin')) {
            if ($cityId = session('current_city_id')) {
                $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
            }
        } elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $user->city_id));
        }

        return $query;
    }
}
