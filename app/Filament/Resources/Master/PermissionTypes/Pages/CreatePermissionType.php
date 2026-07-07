<?php

namespace App\Filament\Resources\Master\PermissionTypes\Pages;

use App\Filament\Resources\Master\PermissionTypes\PermissionTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePermissionType extends CreateRecord
{
    protected static string $resource = PermissionTypeResource::class;
}
