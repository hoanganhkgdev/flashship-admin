<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverLicenseResource\Pages;
use App\Models\DriverLicense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class DriverLicenseResource extends Resource
{
    protected static ?string $model = DriverLicense::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Kiểm duyệt Bằng lái';
    protected static ?string $modelLabel = 'hồ sơ bằng lái';
    protected static ?string $pluralModelLabel = 'Hồ sơ bằng lái';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'QUẢN LÝ TÀI XẾ';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Tài xế')
                    ->disabled(),

                Forms\Components\FileUpload::make('image_path')
                    ->label('Ảnh bằng lái')
                    ->image()
                    ->directory('licenses')
                    ->disk('public')
                    ->disabled(),

                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ duyệt',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('stt')
                    ->label('STT')
                    ->rowIndex()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Tài xế')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => "SĐT: " . ($record->user->phone ?? 'Chưa có')),

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Ảnh bằng lái')
                    ->disk('public')
                    ->size(60)
                    ->square()
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->openUrlInNewTab(fn($record) => asset('storage/' . $record->image_path)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái xác minh')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'Đang chờ',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày gửi hồ sơ')
                    ->dateTime('d/m/Y H:i')
                    ->color('gray')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Lọc theo trạng thái')
                    ->options([
                        'pending' => 'Đang chờ',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve')
                        ->label('Phê duyệt ngay')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->status === 'pending')
                        ->action(fn($record) => $record->update(['status' => 'approved'])),

                    Tables\Actions\EditAction::make()->label('Sửa chi tiết'),
                    Tables\Actions\DeleteAction::make()->label('Xóa hồ sơ'),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->button()
                    ->label('Xử lý'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverLicenses::route('/'),
            'create' => Pages\CreateDriverLicense::route('/create'),
            'edit' => Pages\EditDriverLicense::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // 👑 Admin: xem theo vùng đang chọn
        if ($user->hasRole('admin') && session()->has('current_city_id')) {
            $cityId = session('current_city_id');
            $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
        }
        // 👨‍💼 Manager / Dispatcher: cố định theo city_id của họ
        elseif ($user->hasAnyRole(['manager', 'dispatcher'])) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $user->city_id));
        }

        return $query;
    }
}
