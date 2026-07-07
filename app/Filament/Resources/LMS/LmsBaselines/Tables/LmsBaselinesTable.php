<?php

namespace App\Filament\Resources\LMS\LmsBaselines\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class LmsBaselinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_date')->label('Tanggal Mulai')->date('j F Y'),
                TextColumn::make('end_date')->label('Tanggal Selesai')->date('j F Y'),
                TextColumn::make('min_courses')->label('Jumlah Kursus Minimum')->alignCenter(),
                TextColumn::make('min_hours')->label('Jumlah Jam Minimum')->alignCenter(),
                TextColumn::make('is_active')->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Tidak Aktif')
                    ->badge()
                    ->colors([
                        0 => 'danger',
                        1 => 'success',
                    ])->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
