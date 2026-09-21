<?php

namespace App\Livewire\Dashboard;

use App\Models\Coaching\Coaching;
use Livewire\Component;

/**
 * Widget "Coaching" di Dashboard (Beranda) - jumlah coaching + rencana perbaikan
 * (improvement_due_date) yang paling dekat jatuh tempo.
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

    public function getTotalProperty(): int
    {
        return Coaching::query()->where('company_id', $this->companyId)->count();
    }

    /** Coaching dengan target perbaikan yang belum lewat, dari yang paling dekat jatuh tempo. */
    public function getUpcomingDueProperty()
    {
        return Coaching::query()
            ->where('company_id', $this->companyId)
            ->whereDate('improvement_due_date', '>=', today())
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
