<?php

namespace App\Filament\Resources\LMS\TrainerScoreRanges\Pages;

use App\Filament\Resources\LMS\TrainerScoreRanges\TrainerScoreRangesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainerScoreRanges extends EditRecord
{
    protected static string $resource = TrainerScoreRangesResource::class;
    protected static ?string $title = 'Edit Skala Nilai Trainer';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
