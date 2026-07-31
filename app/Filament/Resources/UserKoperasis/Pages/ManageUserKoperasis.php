<?php

namespace App\Filament\Resources\UserKoperasis\Pages;

use App\Filament\Resources\UserKoperasis\UserKoperasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUserKoperasis extends ManageRecords
{
    protected static string $resource = UserKoperasiResource::class;

    protected static ?string $title = 'Koperasi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Anggota'),
        ];
    }
}
