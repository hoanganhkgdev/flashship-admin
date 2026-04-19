<?php

namespace App\Filament\Resources\DriverWalletResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
            ]);
    }

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
                        'debit' => 'Trừ tiền',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ]),

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
                        'debit' => 'Trừ tiền',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
