<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AttendancePoliciesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kebijakan')
                    ->required()
                    ->disabled()
                    ->columnSpanFull()
                    ->maxLength(255),

                TextInput::make('value')
                    ->label('Nilai')
                    ->numeric()
                    ->required()
                    ->minValue(0),
            ]);
    }
}
