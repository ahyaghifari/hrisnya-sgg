<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Permission')
                    ->description('Nama permission dipakai sebagai kunci pengecekan hak akses di kode (mis. "edit_karyawan"). Pola yang dipakai project ini: {aksi}_{resource}, contoh: view_karyawan, edit_karyawan.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Permission')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('contoh: view_laporan_kehadiran')
                            ->helperText('Snake_case, format {aksi}_{resource} (view/create/edit/delete/approve/import + nama fitur).'),

                        Select::make('guard_name')
                            ->label('Guard')
                            ->options(['web' => 'web'])
                            ->default('web')
                            ->required(),
                    ]),
            ]);
    }
}
