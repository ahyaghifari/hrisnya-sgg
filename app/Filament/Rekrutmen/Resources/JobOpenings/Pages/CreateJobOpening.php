<?php

namespace App\Filament\Rekrutmen\Resources\JobOpenings\Pages;

use App\Filament\Rekrutmen\Resources\JobOpenings\JobOpeningResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobOpening extends CreateRecord
{
    protected static string $resource = JobOpeningResource::class;

    /** Lowongan baru selalu mulai sebagai draft — publish lewat tombol di daftar lowongan. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] = false;
        $data['created_by_name'] = filament()->auth()->user()?->name;

        return $data;
    }
}
