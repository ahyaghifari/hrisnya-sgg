<?php

namespace App\Filament\Resources\Master\Competency\Pages;

use App\Filament\Resources\Master\Competency\CompetencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompetency extends EditRecord
{
    protected static string $resource = CompetencyResource::class;
    protected static ?string $title = 'Edit Kompetensi';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
