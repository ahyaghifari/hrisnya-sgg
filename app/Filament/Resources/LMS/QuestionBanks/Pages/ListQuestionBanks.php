<?php

namespace App\Filament\Resources\LMS\QuestionBanks\Pages;

use App\Filament\Resources\LMS\QuestionBanks\QuestionBanksResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuestionBanks extends ListRecords
{
    protected static string $resource = QuestionBanksResource::class;
    protected static ?string $title = 'Daftar Bank Soal';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Bank Soal Baru')->visible(QuestionBanksResource::canCreate()),
        ];
    }
}
