<?php

namespace App\Filament\Resources\LMS\QuestionBanks\Pages;

use App\Filament\Resources\LMS\QuestionBanks\QuestionBanksResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuestionBanks extends EditRecord
{
    protected static string $resource = QuestionBanksResource::class;
    protected static ?string $title = 'Edit Bank Soal';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(QuestionBanksResource::canDelete($this->record))
        ];
    }
}
