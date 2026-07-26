<?php

namespace App\Filament\Resources\LMS\Trainers\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrainersForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'internal' => 'Internal',
                        'external' => 'External',
                    ])
                    ->native(false)
                    ->default('external')
                    ->required(),

                Select::make('user_id')
                    ->label('Karyawan')
                    ->helperText('Isi kalau pemateri ini karyawan sendiri (tipe Internal).')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                TextInput::make('expertise')
                    ->label('Keahlian')
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(255),

                TextInput::make('company')
                    ->label('Perusahaan Asal')
                    ->maxLength(255),

                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
