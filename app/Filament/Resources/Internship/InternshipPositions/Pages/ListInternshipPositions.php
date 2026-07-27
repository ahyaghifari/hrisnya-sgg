<?php

namespace App\Filament\Resources\Internship\InternshipPositions\Pages;

use App\Filament\Resources\Internship\InternshipPositions\InternshipPositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListInternshipPositions extends ListRecords
{
    protected static string $resource = InternshipPositionResource::class;
    protected static ?string $title = 'Posisi Magang';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Posisi Magang')
                ->slideOver()
                ->modalWidth(Width::Medium)
                ->modalHeading('Tambah Posisi Magang'),
        ];
    }
}
