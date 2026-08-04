<?php

namespace App\Filament\Resources\Master\PermissionTypes\Pages;

use App\Filament\Resources\Master\PermissionTypes\PermissionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePermissionTypes extends ManageRecords
{
    protected static string $resource = PermissionTypeResource::class;

    protected static ?string $title = 'Jenis Izin';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Jenis Izin'),
        ];
    }
}
