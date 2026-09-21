<?php

namespace App\Filament\Resources\Coachings\Pages;

use App\Filament\Resources\Coachings\CoachingResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Read-only — coaching satu arah (instruksi user 2026-09-22): tidak ada approve/reject/mulai,
 * halaman ini cuma menampilkan isi coaching (infolist di CoachingForm::editSchema()).
 */
class EditCoaching extends EditRecord
{
    protected static string $resource = CoachingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getFormActions(): array
    {
        return [];
    }
}
