<?php

namespace App\Filament\Resources\UserContracts\Pages;

use App\Filament\Resources\UserContracts\UserContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUserContracts extends ManageRecords
{
    protected static string $resource = UserContractResource::class;

    protected static ?string $title = 'Data Kontrak';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kontrak'),
        ];
    }
}
