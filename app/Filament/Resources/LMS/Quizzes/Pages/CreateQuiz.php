<?php

namespace App\Filament\Resources\LMS\Quizzes\Pages;

use App\Filament\Resources\LMS\Quizzes\QuizResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuiz extends CreateRecord
{
    protected static string $resource = QuizResource::class;
    protected static ?string $title = 'Buat Kuis Baru';
}
