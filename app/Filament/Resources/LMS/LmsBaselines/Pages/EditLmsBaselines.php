<?php

namespace App\Filament\Resources\LMS\LmsBaselines\Pages;

use App\Filament\Resources\LMS\LmsBaselines\LmsBaselinesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLmsBaselines extends EditRecord
{
    protected static string $resource = LmsBaselinesResource::class;

    protected static ?string $title = 'Edit Baseline Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
