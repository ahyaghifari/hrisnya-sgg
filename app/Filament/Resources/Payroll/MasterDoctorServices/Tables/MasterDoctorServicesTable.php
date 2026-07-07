<?php

namespace App\Filament\Resources\Payroll\MasterDoctorServices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MasterDoctorServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Item')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('calculation_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fixed' => 'Fixed',
                        'percentage' => 'Percentage',
                        'formula' => 'Formula',
                        default => $state,
                    }),

                TextColumn::make('value')
                    ->label('Nilai')
                    ->getStateUsing(function ($record) {
                        return match ($record->calculation_type) {
                            'fixed' => 'Rp ' . number_format($record->amount, 0, ',', '.'),
                            'percentage' => $record->percentage . '%',
                            'formula' => $record->formula_code ?? 'Formula',
                            default => '-',
                        };
                    })
                    ->sortable()
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Tambah Jasa Dokter'),
            ])
            ->toolbarActions([
//                BulkActionGroup::make([
//                    DeleteBulkAction::make(),
//                ]),
            ]);
    }
}
