<?php

namespace App\Filament\Rekrutmen\Resources\Quizzes\Pages;

use App\Filament\Rekrutmen\Resources\Quizzes\QuizResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuizzes extends ManageRecords
{
    protected static string $resource = QuizResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
