<?php

namespace App\Filament\Rekrutmen\Resources\JobOpenings\Pages;

use App\Filament\Rekrutmen\Resources\JobOpenings\JobOpeningResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobOpening extends CreateRecord
{
    protected static string $resource = JobOpeningResource::class;

    /** State persetujuan hasil dehidrasi form (RichEditor -> string), disimpan di afterCreate. */
    protected array $agreementData = [];

    /** Lowongan baru selalu mulai sebagai draft — publish lewat tombol di daftar lowongan. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] = false;
        $data['created_by_name'] = filament()->auth()->user()?->name;

        $this->agreementData = [
            'free_agreements' => $data['free_agreements'] ?? [],
            'opening_agreements' => $data['opening_agreements'] ?? [],
        ];

        return $data;
    }

    protected function afterCreate(): void
    {
        JobOpeningResource::persistAgreements($this->record, $this->agreementData);
    }
}
