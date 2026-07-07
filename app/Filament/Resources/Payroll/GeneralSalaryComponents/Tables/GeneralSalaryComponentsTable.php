<?php

namespace App\Filament\Resources\Payroll\GeneralSalaryComponents\Tables;

use App\Enum\Salary\CalculationTypeEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GeneralSalaryComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Komponen Gaji')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe Komponen')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'earning' => 'Earning (Pendapatan)',
                            'deduction' => 'Deduction (Potongan)',
                            default => ucfirst($state),
                        };
                    })
                    ->searchable(),

                TextColumn::make('calculation_type')
                    ->label('Metode Perhitungan')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'fixed' => 'Tetap',
                            'percentage' => 'Persentase',
                            'formula' => 'Formula',
                            default => ucfirst($state),
                        };
                    })
                    ->searchable(),

                TextColumn::make('component_value')
                    ->label('Nilai')
                    ->getStateUsing(function ($record) {
                        $method = $record->calculation_type;
                        $enum = CalculationTypeEnum::tryFrom($method);

                        return match ($enum) {
                            CalculationTypeEnum::FIXED =>
                                'IDR ' . number_format($record->default_value, 0, ',', '.'),

                            CalculationTypeEnum::PERCENTAGE =>
                                number_format($record->default_value, 0, ',', '.') . '%',

                            CalculationTypeEnum::FORMULA =>
                            'Formula',

                            default => '-',
                        };
                    })
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Edit Komponen Gaji'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
