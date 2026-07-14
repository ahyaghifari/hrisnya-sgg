<?php

namespace App\Filament\Resources\LMS\TrainerScoreRanges\Pages;

use App\Filament\Resources\LMS\TrainerScoreRanges\TrainerScoreRangesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainerScoreRanges extends CreateRecord
{
    protected static string $resource = TrainerScoreRangesResource::class;
    protected static ?string $title = 'Buat Skala Nilai Trainer';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
