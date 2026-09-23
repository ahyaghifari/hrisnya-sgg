<?php

namespace App\Filament\Resources\MeritPointTransactions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeritPointTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('rule.name')
                    ->label('Aturan')
                    ->placeholder('Manual Bebas'),

                TextColumn::make('points')
                    ->label('Poin')
                    ->sortable()
                    ->color(fn (int $state) => $state >= 0 ? 'success' : 'danger'),

                TextColumn::make('reason')
                    ->label('Catatan')
                    ->limit(60),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
