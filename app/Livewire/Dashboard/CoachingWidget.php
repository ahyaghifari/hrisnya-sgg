<?php

namespace App\Livewire\Dashboard;

use App\Models\Coaching\Coaching;
use App\Models\Sp\EmployeeSp;
use Livewire\Component;

/**
 * Widget "Coaching & SP" di Dashboard (Beranda) - jumlah coaching + rencana perbaikan
 * (improvement_due_date) yang paling dekat jatuh tempo, plus rangkuman siapa saja yang sedang
 * kena SP aktif (instruksi user 2026-09-25: digabung ke widget yang sama, bukan widget baru).
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

    /** SP yang sedang aktif (belum expired) - scopeActive() sama persis yang dipakai
     * UserHrBalance/portal SP, bukan hitungan baru. */
    public function getActiveSpTotalProperty(): int
    {
        return EmployeeSp::query()->where('company_id', $this->companyId)->active()->count();
    }

    public function getActiveSpListProperty()
    {
        return EmployeeSp::query()
            ->where('company_id', $this->companyId)
            ->active()
            ->with('user')
            ->orderByDesc('start_date')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.dashboard.coaching-widget');
    }
}
