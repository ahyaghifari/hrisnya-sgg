<?php

namespace App\Filament\Resources\OrientationSubmissions\Pages;

use App\Filament\Resources\OrientationSubmissions\OrientationSubmissionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOrientationSubmissions extends ManageRecords
{
    protected static string $resource = OrientationSubmissionResource::class;

    protected static ?string $title = 'Pengajuan Orientasi';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
