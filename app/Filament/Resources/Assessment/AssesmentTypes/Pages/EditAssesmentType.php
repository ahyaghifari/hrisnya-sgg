<?php

namespace App\Filament\Resources\Assessment\AssesmentTypes\Pages;

use App\Filament\Resources\Assessment\AssesmentTypes\AssesmentTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssesmentType extends EditRecord
{
    protected static string $resource = AssesmentTypeResource::class;

    protected static ?string $title = 'Edit Tipe Penilaian';

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
