<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiKnowledgeResource\Pages;
use App\Filament\Resources\AiKnowledgeResource\RelationManagers;
use App\Models\AiKnowledge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AiKnowledgeResource extends Resource
{
    protected static ?string $model = AiKnowledge::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Huấn luyện AI';
    protected static ?string $modelLabel = 'Kiến thức AI';
    protected static ?string $pluralModelLabel = 'Kho kiến thức AI';
    protected static ?string $navigationGroup = 'CẤU HÌNH AI & OA';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Huấn luyện AI')
                    ->description('Cung cấp ví dụ hoặc quy tắc để AI hiểu khách hàng tốt hơn.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Tên gợi nhớ')
                            ->placeholder('Ví dụ: Đơn giao hàng mẫu 1')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->label('Loại huấn luyện')
                            ->options([
                                'example' => 'Ví dụ mẫu (Few-shot)',
                                'shortcut' => 'Từ khóa địa phương (Shortcut)',
                                'rule' => 'Quy tắc ưu tiên (Rule)',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('city_id')
                            ->label('Khu vực áp dụng')
                            ->relationship('city', 'name')
                            ->placeholder('Tất cả khu vực')
                            ->nullable()
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Trạng thái')
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('input_text')
                            ->label(fn($get) => match ($get('type')) {
                                'example' => 'Tin nhắn của khách mẫu',
                                'shortcut' => 'Từ khóa viết tắt',
                                'rule' => 'Khi thấy từ khóa này...',
                                default => 'Nội dung đầu vào',
                            })
                            ->placeholder(fn($get) => match ($get('type')) {
                                'example' => 'Ví dụ: bún đậu mạc cửu giao 26 võ thị sáu 0912345678',
                                'shortcut' => 'Ví dụ: BV Tỉnh',
                                'rule' => 'Ví dụ: Gấp, hỏa tốc',
                                default => '',
                            })
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('output_data')
                            ->label('Dữ liệu kết quả mong muốn')
                            ->visible(fn($get) => $get('type') === 'example')
                            ->helperText('Nhập các trường như: service_type, delivery_address, delivery_phone...')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('output_data')
                            ->label('Văn bản thay thế (Full Address)')
                            ->visible(fn($get) => $get('type') === 'shortcut')
                            ->placeholder('Ví dụ: Bệnh viện Đa khoa tỉnh Kiên Giang')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('output_data')
                            ->label('Hành động của AI')
                            ->visible(fn($get) => $get('type') === 'rule')
                            ->placeholder('Ví dụ: Đánh dấu đơn này là ưu tiên và cộng thêm 5k phụ phí')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'example' => 'Ví dụ',
                        'shortcut' => 'Từ khóa',
                        'rule' => 'Quy tắc',
                        default => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        'example' => 'info',
                        'shortcut' => 'success',
                        'rule' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Khu vực')
                    ->placeholder('Global')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('input_text')
                    ->label('Đầu vào')
                    ->limit(50),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Kích hoạt'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->label('Khu vực')
                    ->relationship('city', 'name'),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'example' => 'Ví dụ mẫu',
                        'shortcut' => 'Từ khóa',
                        'rule' => 'Quy tắc',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiKnowledge::route('/'),
            'create' => Pages\CreateAiKnowledge::route('/create'),
            'edit' => Pages\EditAiKnowledge::route('/{record}/edit'),
        ];
    }
}
