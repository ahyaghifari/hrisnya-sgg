<?php

namespace App\Filament\Resources\LMS\LmsFeedbackQuestions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LmsFeedbackQuestionsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group_label')
                    ->label('Kelompok')
                    ->helperText('Pengelompokan tampilan, mis. Instruktur, Materi, Fasilitas.')
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('question')
                    ->label('Pertanyaan')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }
}
