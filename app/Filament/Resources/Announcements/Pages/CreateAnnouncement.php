<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected static ?string $title = 'Buat Pengumuman';

    protected function getHeaderActions(): array
    {
        return [AnnouncementResource::previewAction()];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = auth()->user()->company_id;
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        AnnouncementResource::syncTargets($this->record, $this->data['job_position_ids'] ?? []);
    }
}
