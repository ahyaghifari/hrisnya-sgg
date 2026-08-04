<?php

namespace App\Filament\Resources\Master\OvertimeTypes\Pages;

use App\Filament\Resources\Master\OvertimeTypes\OvertimeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOvertimeTypes extends ManageRecords
{
    protected static string $resource = OvertimeTypeResource::class;

    protected static ?string $title = 'Jenis Lembur';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Jenis Lembur'),
        ];
    }
}
