<?php

namespace App\Filament\Resources\EmployeeOrientations\Pages;

use App\Filament\Resources\EmployeeOrientations\EmployeeOrientationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeOrientations extends ManageRecords
{
    protected static string $resource = EmployeeOrientationResource::class;

    protected static ?string $title = 'Data Orientasi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Orientasi')
                // Baris manual dari admin panel (bukan dari onboarding Rekrutmen atau
                // perpanjangan) - lihat komentar EmployeeOrientationResource.
                ->mutateFormDataUsing(function (array $data): array {
                    $data['source'] = 'manual';

                    return $data;
                }),
        ];
    }
}
