<?php

namespace App\Filament\Resources\LMS\LmsBaselines\Pages;

use App\Filament\Resources\LMS\LmsBaselines\LmsBaselinesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLmsBaselines extends ListRecords
{
    protected static string $resource = LmsBaselinesResource::class;
    protected static ?string $title = 'Baseline Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Baseline Pelatihan'),
        ];
    }
}
