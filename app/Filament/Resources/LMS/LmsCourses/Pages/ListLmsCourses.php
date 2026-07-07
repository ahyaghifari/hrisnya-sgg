<?php

namespace App\Filament\Resources\LMS\LmsCourses\Pages;

use App\Filament\Resources\LMS\LmsCourses\LmsCoursesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLmsCourses extends ListRecords
{
    protected static string $resource = LmsCoursesResource::class;
    protected static ?string $title = 'Pelatihan LMS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Pelatihan')
                ->visible(LmsCoursesResource::canCreate()),
        ];
    }
}
