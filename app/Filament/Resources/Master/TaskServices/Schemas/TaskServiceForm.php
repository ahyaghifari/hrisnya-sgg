<?php

namespace App\Filament\Resources\Master\TaskServices\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TaskServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Layanan Penugasan')
                    ->columnSpanFull()
                    ->description('Form untuk menambahkan atau mengedit data Layanan Penugasan.')
                    ->schema([
                        Hidden::make('company_id')
                            ->formatStateUsing(fn () => Auth::user()->company_id)
                            ->default(fn () => Auth::user()->company_id),
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
