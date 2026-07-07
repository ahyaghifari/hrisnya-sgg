<?php

namespace App\Filament\Resources\Payroll\GeneralSalaryComponents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GeneralSalaryComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Komponen Gaji')
                    ->disabled()
                    ->columnSpanFull()
                    ->maxLength(255),

                Select::make('type')
                    ->label('Tipe Komponen')
                    ->options([
                        'earning' => 'Pendapatan',
                        'deduction' => 'Potongan',
                    ])
                    ->disabled()
                    ->columnSpanFull(),

                Select::make('calculation_type')
                    ->label('Metode Perhitungan')
                    ->options([
                        'fixed' => 'Tetap',
                        'percentage' => 'Persentase',
                        'formula' => 'Formula',
                    ])
                    ->disabled()
                    ->columnSpanFull(),

                TextInput::make('default_value')
                    ->label('Nilai')
                    ->numeric()
                    ->columnSpanFull(),
            ]);
    }
}
