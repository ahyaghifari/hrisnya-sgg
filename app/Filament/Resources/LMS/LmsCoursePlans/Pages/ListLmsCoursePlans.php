<?php

namespace App\Filament\Resources\LMS\LmsCoursePlans\Pages;

use App\Filament\Resources\LMS\LmsCoursePlans\LmsCoursePlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLmsCoursePlans extends ListRecords
{
    protected static string $resource = LmsCoursePlanResource::class;
    protected static ?string $title = 'Daftar Rencana Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Rencana Pelatihan Baru'),
        ];
    }
}
