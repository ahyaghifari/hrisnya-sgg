<?php

namespace App\Filament\Resources\LMS\QuestionBanks\Pages;

use App\Filament\Resources\LMS\QuestionBanks\QuestionBanksResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestionBanks extends CreateRecord
{
    protected static string $resource = QuestionBanksResource::class;
    protected static ?string $title = 'Buat Bank Soal Baru';
}
