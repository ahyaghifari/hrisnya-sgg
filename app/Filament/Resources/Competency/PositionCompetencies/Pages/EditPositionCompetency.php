<?php

namespace App\Filament\Resources\Competency\PositionCompetencies\Pages;

use App\Filament\Resources\Competency\PositionCompetencies\PositionCompetencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPositionCompetency extends EditRecord
{
    protected static string $resource = PositionCompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
