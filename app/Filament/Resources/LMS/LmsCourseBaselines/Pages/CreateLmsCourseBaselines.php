<?php

namespace App\Filament\Resources\LMS\LmsCourseBaselines\Pages;

use App\Filament\Resources\LMS\LmsCourseBaselines\LmsCourseBaselinesResource;
use App\Models\LMS\LmsCourseBaselines;
use Filament\Resources\Pages\CreateRecord;

class CreateLmsCourseBaselines extends CreateRecord
{
    protected static string $resource = LmsCourseBaselinesResource::class;

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
