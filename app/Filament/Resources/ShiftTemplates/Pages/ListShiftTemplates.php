<?php

namespace App\Filament\Resources\ShiftTemplates\Pages;

use App\Filament\Resources\ShiftTemplates\ShiftTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftTemplates extends ListRecords
{
    protected static string $resource = ShiftTemplateResource::class;

    protected static ?string $title = 'Template Shift';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Template Shift Baru')
                ->visible(fn () => ShiftTemplateResource::canCreate()),
        ];
    }
}
