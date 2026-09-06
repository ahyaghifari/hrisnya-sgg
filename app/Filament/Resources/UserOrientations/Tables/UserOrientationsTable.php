<?php

namespace App\Filament\Resources\UserOrientations\Tables;

use App\Filament\Resources\UserOrientations\UserOrientationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class UserOrientationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Sampai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('duration_months')
                    ->label('Durasi')
                    ->suffix(' bln')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn ($record) => $record->end_date && Carbon::parse($record->end_date)->isPast()
                        ? 'Berakhir'
                        : 'Aktif')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Aktif' ? 'success' : 'danger'),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'recruitment' => 'Rekrutmen',
                        'extension' => 'Perpanjangan',
                        'manual' => 'Manual',
                        default => $state ?? '-',
                    })
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'recruitment' => 'info',
                        'extension' => 'warning',
                        default => 'gray',
                    }),

                UserOrientationResource::companyTableColumn(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                UserOrientationResource::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
