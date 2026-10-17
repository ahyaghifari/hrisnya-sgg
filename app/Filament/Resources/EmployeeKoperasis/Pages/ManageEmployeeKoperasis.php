<?php

namespace App\Filament\Resources\EmployeeKoperasis\Pages;

use App\Filament\Resources\EmployeeKoperasis\EmployeeKoperasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeKoperasis extends ManageRecords
{
    protected static string $resource = EmployeeKoperasiResource::class;

    protected static ?string $title = 'Koperasi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Anggota'),
        ];
    }
}
