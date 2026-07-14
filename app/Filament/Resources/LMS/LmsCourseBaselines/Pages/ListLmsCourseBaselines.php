<?php

namespace App\Filament\Resources\LMS\LmsCourseBaselines\Pages;

use App\Filament\Resources\LMS\LmsCourseBaselines\LmsCourseBaselinesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLmsCourseBaselines extends ListRecords
{
    protected static string $resource = LmsCourseBaselinesResource::class;
    protected static ?string $title = 'Baseline Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Baseline Pelatihan'),
        ];
    }
}
