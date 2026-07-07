<?php

namespace App\Filament\Resources\LMS\Quizzes\Pages;

use App\Filament\Resources\LMS\Quizzes\QuizResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuiz extends EditRecord
{
    protected static string $resource = QuizResource::class;
    protected static ?string $title = 'Edit Kuis';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(QuizResource::canDelete($this->record))
        ];
    }
}
