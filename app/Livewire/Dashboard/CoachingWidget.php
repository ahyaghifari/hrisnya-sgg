<?php

namespace App\Livewire\Dashboard;

use App\Enum\CoachingStatus;
use App\Models\Coaching\Coaching;
use Livewire\Component;

/**
 * Widget "Coaching" di Dashboard (Beranda) - jumlah per status + rencana perbaikan
 * (improvement_due_date) yang paling dekat jatuh tempo, biar HC Manager tahu coaching mana yang
 * butuh ditindaklanjuti.
 *
 * Namespace App\Livewire\Dashboard - widget ini murni milik halaman Beranda (instruksi user
 * 2026-09-18), bukan bagian modul Kepegawaian.
 *
 * Gate: sama seperti widget lain di dashboard ini, lihat
 * Dashboard::getCanKepegawaianWidgetsProperty().
 */
class CoachingWidget extends Component
{
    public string $companyId;

    public function getStatusCountsProperty(): array
    {
        $counts = Coaching::query()
            ->where('company_id', $this->companyId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(CoachingStatus::cases())
            ->mapWithKeys(fn (CoachingStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /** Coaching yang masih aktif (belum selesai/ditolak) dan punya target perbaikan,
     * diurutkan dari yang paling dekat jatuh tempo dulu. */
    public function getUpcomingDueProperty()
    {
        return Coaching::query()
            ->where('company_id', $this->companyId)
            ->whereIn('status', [CoachingStatus::PENDING->value, CoachingStatus::IN_PROGRESS->value])
            ->whereNotNull('improvement_due_date')
            ->with(['user', 'coachBy'])
            ->orderBy('improvement_due_date')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.dashboard.coaching-widget');
    }
}
