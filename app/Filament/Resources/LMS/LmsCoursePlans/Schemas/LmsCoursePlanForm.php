<?php

namespace App\Filament\Resources\LMS\LmsCoursePlans\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class LmsCoursePlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rencana Pelatihan')
                    ->footerActionsAlignment(Alignment::Center)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Hidden::make('created_by')
                                    ->formatStateUsing(fn () => auth()->id())
                                    ->default(auth()->id()),
                                TextInput::make('title')
                                    ->label('Judul Rencana Pelatihan')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('year')
                                    ->label('Tahun')
                                    ->required()
                                    ->maxLength(4)
                                    ->columnSpan(1),

                                Select::make('type_nakes')
                                    ->label('Tipe Peserta')
                                    ->options([
                                        'nakes' => 'Nakes',
                                        'non-nakes' => 'Non-Nakes',
                                        'all' => 'Semua',
                                    ])
                                    ->default('non-nakes')
                                    ->required()
                                    ->columnSpan(1),

                                Textarea::make('notes')
                                    ->label('Catatan')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                    ]),
            ]);
    }
}
