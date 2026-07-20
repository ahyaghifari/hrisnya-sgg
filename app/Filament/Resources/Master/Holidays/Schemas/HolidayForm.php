<?php

namespace App\Filament\Resources\Master\Holidays\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->native(false),

                Toggle::make('is_collective_leave')
                    ->label('Cuti Bersama')
                    ->helperText('Aktifkan kalau tanggal ini mengurangi jatah Cuti Tahunan karyawan. Nonaktif = libur nasional biasa, tidak mengurangi cuti.')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
