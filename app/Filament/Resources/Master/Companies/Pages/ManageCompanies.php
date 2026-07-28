<?php

namespace App\Filament\Resources\Master\Companies\Pages;

use App\Filament\Resources\Master\Companies\CompaniesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompanies extends ManageRecords
{
    protected static string $resource = CompaniesResource::class;

    protected static ?string $title = 'Perusahaan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Perusahaan')
                ->visible(fn () => CompaniesResource::canCreate()),
        ];
    }
}
