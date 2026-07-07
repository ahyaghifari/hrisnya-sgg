<?php

namespace App\Filament\Resources\Master\PermissionTypes\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PermissionTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')
                    ->dehydrated()
                    ->default(fn () => Auth::user()->company_id),
                TextInput::make('name')
                    ->label('Nama Tipe Izin')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
            ]);
    }
}
