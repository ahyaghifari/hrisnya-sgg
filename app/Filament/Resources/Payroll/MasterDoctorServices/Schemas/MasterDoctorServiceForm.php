<?php

namespace App\Filament\Resources\Payroll\MasterDoctorServices\Schemas;

use App\Enum\Salary\CalculationTypeEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MasterDoctorServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Item Jasa')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Hidden::make('calculation_type')
                    ->default(CalculationTypeEnum::FIXED->value),

                TextInput::make('amount')
                    ->label('Nominal (Fixed)')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
