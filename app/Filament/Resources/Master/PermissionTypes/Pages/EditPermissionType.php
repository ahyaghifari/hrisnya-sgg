<?php

namespace App\Filament\Resources\Master\PermissionTypes\Pages;

use App\Filament\Resources\Master\PermissionTypes\PermissionTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPermissionType extends EditRecord
{
    protected static string $resource = PermissionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
