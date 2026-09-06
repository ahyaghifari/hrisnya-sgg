<?php

namespace App\Filament\Resources\UserOrientations\Pages;

use App\Filament\Resources\UserOrientations\UserOrientationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUserOrientations extends ManageRecords
{
    protected static string $resource = UserOrientationResource::class;

    protected static ?string $title = 'Data Orientasi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Orientasi')
                // Baris manual dari admin panel (bukan dari onboarding Rekrutmen atau
                // perpanjangan) - lihat komentar UserOrientationResource.
                ->mutateFormDataUsing(function (array $data): array {
                    $data['source'] = 'manual';

                    return $data;
                }),
        ];
    }
}
