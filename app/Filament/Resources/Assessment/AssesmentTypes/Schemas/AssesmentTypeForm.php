<?php

namespace App\Filament\Resources\Assessment\AssesmentTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssesmentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Tipe Penilaian')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Deskripsi Tipe Penilaian')
                    ->rows(3)
                    ->columnSpanFull()
                    ->maxLength(65535)
                    ->nullable(),
            ]);
    }
}
