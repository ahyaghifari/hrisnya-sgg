<?php

namespace App\Filament\Resources\LMS\TrainerScoreVariables\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TrainerScoreVariableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Variabel Gaji Trainer')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('company_id')
                            ->dehydrated()
                            ->default(fn () => Auth::user()->company_id),
                        TextInput::make('name')
                            ->label('Nama Variabel')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label('Tipe Variabel')
                            ->options([
                                'honor' => 'Honor',
                                'validity' => 'Plafon',
                            ])
                            ->required(),

                        Textarea::make('description')
                            ->label('Deskripsi Variabel (Opsional)')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),

                Repeater::make('categories')
                    ->relationship('categories')
                    ->itemLabel('Kategori Skor')
                    ->columns(2)
                    ->addActionLabel('Tambah Kategori Skor')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kategori')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('score')
                            ->label('Skor')
                            ->numeric()
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columnSpanFull(),
            ]);
    }
}
