<?php

namespace App\Filament\Resources\LMS\Quizzes\Pages;

use App\Filament\Resources\LMS\Quizzes\QuizResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuizzes extends ListRecords
{
    protected static string $resource = QuizResource::class;
    protected static ?string $title = 'Daftar Kuis';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Kuis Baru')->visible(QuizResource::canCreate()),
        ];
    }
}
