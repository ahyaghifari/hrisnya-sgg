<?php

namespace App\Filament\Resources\LMS\LmsFeedbackQuestions\Pages;

use App\Filament\Resources\LMS\LmsFeedbackQuestions\LmsFeedbackQuestionsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLmsFeedbackQuestions extends ListRecords
{
    protected static string $resource = LmsFeedbackQuestionsResource::class;
    protected static ?string $title = 'Pertanyaan Evaluasi Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Pertanyaan')->visible(LmsFeedbackQuestionsResource::canCreate()),
        ];
    }
}
