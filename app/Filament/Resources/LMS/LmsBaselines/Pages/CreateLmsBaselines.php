<?php

namespace App\Filament\Resources\LMS\LmsBaselines\Pages;

use App\Filament\Resources\LMS\LmsBaselines\LmsBaselinesResource;
use App\Models\LMS\LmsCourseBaselines;
use Filament\Resources\Pages\CreateRecord;

class CreateLmsBaselines extends CreateRecord
{
    protected static string $resource = LmsBaselinesResource::class;

    protected static ?string $title = 'Tambah Baseline Pelatihan';

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        LmsCourseBaselines::where('company_id', $record->company_id)
            ->where('id', '!=', $record->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
