<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiEscalationResource\Pages;
use App\Models\AiEscalation;
use App\Models\ZaloAccount;
use App\Services\ZaloService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class AiEscalationResource extends Resource
{
    protected static ?string $model = AiEscalation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'AI Escalations';
    protected static ?string $navigationGroup = 'CẤU HÌNH AI & OA';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Escalation';
    protected static ?string $pluralModelLabel = 'Escalations';

    /**
     * Badge đỏ hiển thị số escalation đang mở
     */
    public static function getNavigationBadge(): ?string
    {
        return (string) AiEscalation::where('status', 'open')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return AiEscalation::where('status', 'open')->where('urgency', 'high')->exists()
            ? 'danger'
            : 'warning';
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin Escalation')->schema([
                Forms\Components\TextInput::make('sender_id')->label('Zalo ID Khách')->disabled(),
                Forms\Components\TextInput::make('platform')->label('Nền tảng')->disabled(),
                Forms\Components\Select::make('urgency')->label('Mức độ')->options([
                    'low'    => '🟢 Thấp',
                    'medium' => '🟡 Trung bình',
                    'high'   => '🔴 Khẩn cấp',
                ])->disabled(),
                Forms\Components\TextInput::make('reason')->label('Lý do')->disabled(),
            ])->columns(2),

            Forms\Components\Section::make('Tóm tắt hội thoại')->schema([
                Forms\Components\Textarea::make('conversation_summary')
                    ->label('Nội dung')->disabled()->rows(5),
            ]),

            Forms\Components\Section::make('Xử lý')->schema([
                Forms\Components\Select::make('status')->label('Trạng thái')->options([
                    'open'     => 'Đang mở',
                    'resolved' => 'Đã xử lý',
                ]),
                Forms\Components\Textarea::make('resolution_note')
                    ->label('Ghi chú xử lý')->rows(3)
                    ->placeholder('Nhập ghi chú về cách đã xử lý...'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')->sortable()->width('60px'),

                Tables\Columns\BadgeColumn::make('urgency')
                    ->label('Mức độ')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'high'   => '🔴 Khẩn cấp',
                        'medium' => '🟡 Trung bình',
                        'low'    => '🟢 Thấp',
                        default  => $state,
                    })
                    ->colors([
                        'danger'  => 'high',
                        'warning' => 'medium',
                        'success' => 'low',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open'     => '🔓 Đang mở',
                        'resolved' => '✅ Đã xử lý',
                        default    => $state,
                    })
                    ->colors([
                        'warning' => 'open',
                        'success' => 'resolved',
                    ]),

                Tables\Columns\TextColumn::make('sender_id')
                    ->label('Zalo ID Khách')
                    ->copyable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Lý do')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->reason),

                Tables\Columns\TextColumn::make('conversation_summary')
                    ->label('Tóm tắt')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->conversation_summary),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')->dateTime('H:i d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Giải quyết lúc')->dateTime('H:i d/m/Y')
                    ->placeholder('Chưa xử lý'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'open'     => '🔓 Đang mở',
                        'resolved' => '✅ Đã xử lý',
                    ])
                    ->default('open'),

                Tables\Filters\SelectFilter::make('urgency')
                    ->label('Mức độ')
                    ->options([
                        'high'   => '🔴 Khẩn cấp',
                        'medium' => '🟡 Trung bình',
                        'low'    => '🟢 Thấp',
                    ]),
            ])
            ->actions([
                // Nút Resolve & Kích hoạt lại AI
                Tables\Actions\Action::make('resolve')
                    ->label('✅ Resolve & Bật AI')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->status === 'open')
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận Resolve')
                    ->modalDescription('Hành động này sẽ đóng escalation và kích hoạt lại AI trả lời cho khách.')
                    ->form([
                        Forms\Components\Textarea::make('resolution_note')
                            ->label('Ghi chú xử lý (không bắt buộc)')
                            ->placeholder('VD: Đã liên hệ tài xế, đền bù 50k...')
                            ->rows(3),
                        Forms\Components\Toggle::make('notify_customer')
                            ->label('Gửi tin nhắn thông báo cho khách')
                            ->default(true),
                    ])
                    ->action(function (AiEscalation $record, array $data): void {
                        // 1. Đóng escalation
                        $record->update([
                            'status'          => 'resolved',
                            'resolution_note' => $data['resolution_note'] ?? 'Quản lý đã xử lý qua trang admin.',
                            'resolved_at'     => now(),
                        ]);

                        Log::info("Admin Resolve: Escalation #{$record->id} → resolved. AI hoạt động lại cho [{$record->sender_id}]");

                        // 2. Gửi tin nhắn thông báo cho khách (nếu chọn)
                        if ($data['notify_customer'] ?? true) {
                            try {
                                $account = ZaloAccount::where('platform', $record->platform)
                                    ->where('is_active', true)
                                    ->first();

                                if ($account) {
                                    $zaloService = new ZaloService($account);
                                    $sent = $zaloService->sendTextMessage(
                                        $record->sender_id,
                                        "Dạ, vấn đề của anh/chị đã được xử lý xong rồi ạ! 🎉 Anh/chị cần đặt dịch vụ hay hỗ trợ thêm gì cứ nhắn em nhé! 😊"
                                    );
                                    Log::info("Admin Resolve: Gửi thông báo cho [{$record->sender_id}] → " . ($sent ? 'OK' : 'FAILED'));
                                }
                            } catch (\Exception $e) {
                                Log::error("Admin Resolve: Không gửi được tin OA — " . $e->getMessage());
                            }
                        }

                        Notification::make()
                            ->title('✅ Đã Resolve!')
                            ->body("AI đã hoạt động lại cho khách {$record->sender_id}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make()->label('Xem'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_resolve')
                        ->label('✅ Resolve tất cả')
                        ->color('success')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each(function (AiEscalation $record) {
                                if ($record->status === 'open') {
                                    $record->update([
                                        'status'          => 'resolved',
                                        'resolution_note' => 'Bulk resolve bởi admin.',
                                        'resolved_at'     => now(),
                                    ]);
                                }
                            });

                            Notification::make()
                                ->title('✅ Đã Resolve!')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiEscalations::route('/'),
            'view'  => Pages\ViewAiEscalation::route('/{record}'),
        ];
    }
}
