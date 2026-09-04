<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\McuInvitee;
use Filament\Pages\Page;

/**
 * Rincian hasil MCU 1 kandidat (read-only) — port dari HasilMcuDetailPage project
 * `rekrutmen`. `?invitee=<id>`, tak muncul di nav. Link file (EKG/lab/radiologi) ditunda.
 */
class HasilMcuDetailPage extends Page
{
    protected string $view = 'filament.rekrutmen.pages.hasil-mcu-detail-page';

    protected static bool $shouldRegisterNavigation = false;

    public ?McuInvitee $invitee = null;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('View:JobOpenings');
    }

    public function mount(): void
    {
        $id = request('invitee');

        $this->invitee = $id
            ? McuInvitee::with(['nurseAssessment', 'labResult', 'radiologyResult', 'doctorExamination', 'jobApplication.jobOpening', 'jobApplication.answers'])->find($id)
            : null;

        abort_if(! $this->invitee, 404);
    }

    public function getTitle(): string
    {
        return 'Hasil MCU';
    }
}
