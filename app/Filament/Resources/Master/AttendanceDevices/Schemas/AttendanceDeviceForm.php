<?php

namespace App\Filament\Resources\Master\AttendanceDevices\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AttendanceDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')
                    ->dehydrated()
                    ->default(fn () => Auth::user()->company_id),
                TextInput::make('name')
                    ->label('Nama Device')
                    ->helperText('Harus sama persis dengan nama device di HikCentral.')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
