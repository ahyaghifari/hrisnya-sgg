<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;
    protected static ?string $title = 'Daftar Permission';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(PermissionResource::canCreate())
                ->label('Buat Permission')
                ->modalWidth('md'),
        ];
    }
}
