<?php

namespace App\Filament\Resources\Master\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompaniesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tambah/Edit Perusahaan')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Perusahaan')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('location')
                            ->label('Lokasi Perusahaan')
                            ->required()
                            ->maxLength(255),
                            
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->onIcon('heroicon-s-check-circle')
                            ->offIcon('heroicon-s-x-circle')
                            ->onColor('success')
                            ->offColor('danger')
                            ->default(true)
                    ])
                    ->description('Form untuk menambah atau mengedit data perusahaan.'),
            ]);
    }
}
