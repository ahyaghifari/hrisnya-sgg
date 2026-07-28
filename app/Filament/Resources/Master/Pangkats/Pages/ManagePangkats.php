<?php

namespace App\Filament\Resources\Master\Pangkats\Pages;

use App\Filament\Resources\Master\Pangkats\PangkatsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePangkats extends ManageRecords
{
    protected static string $resource = PangkatsResource::class;

    protected static ?string $title = 'Pangkat';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pangkat'),
        ];
    }
}
