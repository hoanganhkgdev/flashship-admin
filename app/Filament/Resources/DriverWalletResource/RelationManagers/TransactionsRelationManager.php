<?php

namespace App\Filament\Resources\DriverWalletResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->heading('Lịch sử giao dịch')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'credit' => 'Cộng tiền',
                        'debit'  => 'Trừ tiền',
                        default  => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        'credit' => 'success',
                        'debit'  => 'danger',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->weight('bold')
                    ->color(fn($record) => $record->type === 'credit' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Nội dung')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Tham chiếu')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại giao dịch')
                    ->options([
                        'credit' => 'Cộng tiền',
                        'debit'  => 'Trừ tiền',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->label('Khoảng thời gian')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
