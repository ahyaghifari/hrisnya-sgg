<?php

namespace App\Filament\Resources\LMS\LmsCoursePlans\Pages;

use App\Filament\Resources\LMS\LmsCoursePlans\LmsCoursePlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLmsCoursePlan extends EditRecord
{
    protected static string $resource = LmsCoursePlanResource::class;
    protected static ?string $title = 'Edit Rencana Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Hapus Rencana Pelatihan'),
        ];
    }
}
