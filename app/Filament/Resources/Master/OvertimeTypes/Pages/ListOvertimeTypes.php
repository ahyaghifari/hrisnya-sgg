<?php

namespace App\Filament\Resources\Master\OvertimeTypes\Pages;

use App\Filament\Resources\Master\OvertimeTypes\OvertimeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListOvertimeTypes extends ListRecords
{
    protected static string $resource = OvertimeTypeResource::class;
    protected static ?string $title = 'Daftar Jenis Lembur';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Jenis Lembur')
                ->slideOver()
                ->modalWidth(Width::Medium)
                ->modalHeading('Tambah Jenis Lembur')
        ];
    }
}
