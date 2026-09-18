<?php

namespace App\Livewire\Dashboard;

use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Models\LMS\LmsCourseUsers;
use App\Models\User;
use App\Models\User\UserContract;
use App\Models\User\UserTransition;
use Livewire\Component;

/**
 * Widget "Summary" di Dashboard (Beranda) - 6 tile ringkas paling atas (Total Karyawan,
 * Karyawan Aktif, Karyawan Baru, Resign Bulan Ini, Kontrak Berakhir, Training Berjalan), pola
 * sama persis planning/dashboard/dashboard-hc-manager.html §3.
 *
 * companyId dipassing dari Dashboard (parent) lewat prop - lihat komentar $companyId di
 * app/Livewire/Dashboard.php buat kenapa bukan baca Auth::user()->company_id sendiri.
 *
 * Gate: sama seperti widget lain di dashboard ini, lihat
 * Dashboard::getCanDashboardHcSummaryProperty().
 */
class SummaryStripWidget extends Component
{
    public string $companyId;

    public function getTilesProperty(): array
    {
        $companyId = $this->companyId;

        return [
            ['label' => 'Total Karyawan', 'value' => $this->totalKaryawan($companyId), 'color' => 'var(--text-heading)', 'icon' => 'fa-users', 'iconClass' => 'neutral'],
            ['label' => 'Karyawan Aktif', 'value' => $this->karyawanAktif($companyId), 'color' => 'var(--text-heading)', 'icon' => 'fa-user-check', 'iconClass' => 'done'],
            ['label' => 'Karyawan Baru', 'value' => $this->karyawanBaru($companyId), 'color' => '#047C54', 'icon' => 'fa-user-plus', 'iconClass' => 'done'],
            ['label' => 'Resign Bulan Ini', 'value' => $this->resignBulanIni($companyId), 'color' => '#b91c1c', 'icon' => 'fa-user-slash', 'iconClass' => 'danger'],
            ['label' => 'Kontrak Berakhir', 'value' => $this->kontrakBerakhir($companyId), 'color' => '#d97706', 'icon' => 'fa-file-contract', 'iconClass' => 'warn'],
            ['label' => 'Training Berjalan', 'value' => $this->trainingBerjalan($companyId), 'color' => 'var(--brand)', 'icon' => 'fa-graduation-cap', 'iconClass' => 'neutral'],
        ];
    }

    private function totalKaryawan(string $companyId): int
    {
        return User::query()->where('company_id', $companyId)->count();
    }

    private function karyawanAktif(string $companyId): int
    {
        return User::query()->where('company_id', $companyId)->where('is_active', true)->count();
    }

    private function karyawanBaru(string $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereHas('profile', fn ($q) => $q->whereBetween('joining_date', [now()->startOfMonth(), now()->endOfMonth()]))
            ->count();
    }

    /** UserTransition type=termination, sudah disetujui, efektif bulan berjalan. */
    private function resignBulanIni(string $companyId): int
    {
        return UserTransition::query()
            ->where('company_id', $companyId)
            ->where('transition_type', UserTransitionTypeEnum::TERMINATION->value)
            ->where('status', UserTransitionStatusEnum::APPROVED->value)
            ->whereBetween('effective_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /** Kontrak (periode TERBARU per user, via User::activeContract()) yang berakhir dalam 90
     * hari ke depan - gabungan bucket Urgent(<30 hari)+Perhatian(30-90 hari) di widget Status
     * Kepegawaian, ditampilkan di sini sebagai 1 angka ringkas. */
    private function kontrakBerakhir(string $companyId): int
    {
        $now = now()->startOfDay();
        $horizon = $now->copy()->addDays(90);

        return UserContract::latestPerUserInCompany($companyId)
            ->filter(fn ($c) => $c->contract_end_date->between($now, $horizon))
            ->count();
    }

    /** Jumlah course berbeda yang punya minimal 1 peserta company ini berstatus in_progress. */
    private function trainingBerjalan(string $companyId): int
    {
        return LmsCourseUsers::query()
            ->whereHas('user', fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'in_progress')
            ->distinct('course_id')
            ->count('course_id');
    }

    public function render()
    {
        return view('livewire.dashboard.summary-strip-widget');
    }
}
