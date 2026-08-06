<?php

namespace App\Filament\Resources\LMS\LmsCourseBaselines\Pages;

use App\Filament\Resources\LMS\LmsCourseBaselines\LmsCourseBaselinesResource;
use App\Models\LMS\LmsCourseBaselines;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLmsCourseBaselines extends ManageRecords
{
    protected static string $resource = LmsCourseBaselinesResource::class;

    protected static ?string $title = 'Baseline Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Baseline Pelatihan')
                ->after(function (LmsCourseBaselines $record) {
                    LmsCourseBaselines::where('company_id', $record->company_id)
                        ->where('id', '!=', $record->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }),
        ];
    }
}
