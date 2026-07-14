<?php

namespace App\Filament\Resources\LMS\LmsCourseBaselines\Pages;

use App\Filament\Resources\LMS\LmsCourseBaselines\LmsCourseBaselinesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLmsCourseBaselines extends EditRecord
{
    protected static string $resource = LmsCourseBaselinesResource::class;

    protected static ?string $title = 'Edit Baseline Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
