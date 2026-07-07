<?php

namespace App\Filament\Resources\Master\LeaaveTypes\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LeaaveTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')
                    ->dehydrated()
                    ->default(fn () => Auth::user()->company_id),

                TextInput::make('name')
                    ->label('Nama Tipe Cuti')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                TextInput::make('quota_days')
                    ->label('Kuota (Hari)')
                    ->required()
                    ->numeric(),

                Select::make('reset_period')
                    ->label('Periode Reset')
                    ->options([
                        'yearly' => 'Tahunan',
                        'monthly' => 'Bulanan',
                        'none' => 'Tidak Ada',
                    ])
                    ->required(),
            ]);
    }
}
