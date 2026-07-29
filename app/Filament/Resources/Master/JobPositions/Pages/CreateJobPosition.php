<?php

namespace App\Filament\Resources\Master\JobPositions\Pages;

use App\Enum\JobPositionReqStatus;
use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobPosition extends CreateRecord
{
    protected static string $resource = JobPositionResource::class;

    protected static ?string $title = 'Ajukan Jabatan Baru';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** DB default kolom status = 'pending' (bukan 'draft') - tanpa ini, jabatan yang baru
     * dibuat langsung terkunci di form Edit (lockedAfterDraft() di JobPositionResource
     * membandingkan ke DRAFT). Submit ke approval flow (JobPositionService::requestJobPosition())
     * ditunda - bukan bagian task ini. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = JobPositionReqStatus::DRAFT->value;

        return $data;
    }
}
