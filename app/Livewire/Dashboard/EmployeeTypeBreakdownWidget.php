<?php

namespace App\Livewire\Dashboard;

use App\Models\OrientationSubmission;
use App\Models\User;
use App\Models\User\UserContract;
use Livewire\Component;

/**
 * Widget "Status Kepegawaian" di Dashboard (Beranda) - breakdown karyawan aktif per
 * EmployeeTypes (Tetap/Kontrak/Orientasi, master data per company). Muncul buat siapa pun yang
 * pegang WidgetPermissionEnum::DASHBOARD_HC_EMPLOYEE_TYPE - dicek di
 * Dashboard::getCanDashboardHcEmployeeTypeProperty() + @if yang membungkus tag ini di
 * home-cards.blade.php, bukan di class ini sendiri (pola sama TeamShiftScheduleCard).
 *
 * Tiap tile tipe kepegawaian & baris "Kontrak/Orientasi Akan Berakhir" bisa diklik buat lihat
 * daftar orangnya (modal) - instruksi user 2026-09-18, pola sama openModal()/closeModal() yang
 * sudah dipakai KepegawaianDashboardPage.
 *
 * Namespace App\Livewire\Dashboard (bukan App\Livewire\Kepegawaian) - widget ini murni milik
 * halaman Beranda, bukan bagian modul Kepegawaian (beda dari KepegawaianDashboardPage yang
 * memang halaman modul tersendiri) - instruksi user 2026-09-18.
 *
 * companyId dipassing dari Dashboard (parent) lewat prop, ikut filter company kalau user
 * pegang LIHAT_PERUSAHAAN_LAIN (bukan selalu company sendiri lagi) - lihat komentar $companyId
 * di app/Livewire/Dashboard.php.
 */
class EmployeeTypeBreakdownWidget extends Component
{
    public string $companyId;

    /** Nama tipe kepegawaian (mis. "Tetap") ATAU salah satu key baris "Akan Berakhir"
     * ('kontrak-urgent'|'orientasi-urgent'|'kontrak-attention') - null kalau modal tertutup. */
    public ?string $activeModal = null;

    public function openModal(string $key): void
    {
        $this->activeModal = $key;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    public function getBreakdownProperty()
    {
        return User::query()
            ->whereCompany($this->companyId)
            ->whereHas('profile.employeeType')
            ->with('profile.employeeType')
            ->get()
            ->groupBy(fn (User $u) => $u->profile?->employeeType?->name ?? '-')
            ->map(fn ($group, $name) => ['name' => $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();
    }

    /** Buat modal tile tipe kepegawaian - daftar user dengan employeeType.name = $activeModal. */
    public function getEmployeesForTypeProperty()
    {
        if (! $this->activeModal) {
            return collect();
        }

        return User::query()
            ->whereCompany($this->companyId)
            ->whereHas('profile.employeeType', fn ($q) => $q->where('name', $this->activeModal))
            ->with('profile.jobPosition')
            ->orderByName()
            ->get();
    }

    /** Warna angka per tipe kepegawaian, cocokkan nama (case-insensitive, master data per
     * company jadi bukan enum tetap) - Tetap hijau, Kontrak navy, Orientasi amber, selain itu
     * fallback navy. Pola warna sama seperti planning/dashboard/dashboard-hc-manager.html. */
    public function colorForType(string $name): string
    {
        $lower = strtolower($name);

        return match (true) {
            str_contains($lower, 'tetap') => '#047C54',
            str_contains($lower, 'orientasi') => '#d97706',
            default => 'var(--brand)',
        };
    }

    /** Kontrak dari periode kontrak TERBARU per user (UserContract, ledger append-only), yang
     * berakhir dalam $maxDays hari ke depan. */
    private function expiringContracts(int $maxDays)
    {
        $now = now()->startOfDay();

        return UserContract::latestPerUserInCompany($this->companyId)
            ->filter(function ($c) use ($now, $maxDays) {
                $days = $now->diffInDays($c->contract_end_date, false);

                return $days >= 0 && $days < $maxDays;
            })
            ->values();
    }

    /** Pengajuan penilaian orientasi yang masih aktif (belum ada keputusan) dan berakhir dalam
     * $maxDays hari ke depan. */
    private function expiringOrientations(int $maxDays)
    {
        $now = now()->startOfDay();

        return OrientationSubmission::query()
            ->whereHas('user', fn ($q) => $q->whereCompany($this->companyId))
            ->whereNull('decision')
            ->whereNotNull('effective_end_date')
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($group) => $group->sortByDesc('effective_end_date')->first())
            ->filter(function ($o) use ($now, $maxDays) {
                $days = $now->diffInDays($o->effective_end_date, false);

                return $days >= 0 && $days < $maxDays;
            })
            ->values();
    }

    /** "Kontrak / Orientasi Akan Berakhir" - 3 baris ringkas dipakai panel widget. */
    public function getExpiringStatusRowsProperty(): array
    {
        return [
            ['key' => 'kontrak-urgent', 'count' => $this->expiringContracts(30)->count(), 'type' => 'Kontrak', 'typeBadge' => 'badge-info', 'desc' => 'Berakhir < 30 hari', 'urgencyBadge' => 'badge-rejected', 'urgencyLabel' => 'Urgent', 'icon' => 'fa-file-contract', 'tile' => 'danger'],
            ['key' => 'orientasi-urgent', 'count' => $this->expiringOrientations(7)->count(), 'type' => 'Orientasi', 'typeBadge' => 'badge-pending', 'desc' => 'Berakhir < 7 hari', 'urgencyBadge' => 'badge-rejected', 'urgencyLabel' => 'Urgent', 'icon' => 'fa-user-clock', 'tile' => 'warn'],
            ['key' => 'kontrak-attention', 'count' => $this->expiringContractsBetween(30, 90)->count(), 'type' => 'Kontrak', 'typeBadge' => 'badge-info', 'desc' => 'Berakhir 30–90 hari', 'urgencyBadge' => 'badge-pending', 'urgencyLabel' => 'Perhatian', 'icon' => 'fa-file-contract', 'tile' => 'warn'],
        ];
    }

    private function expiringContractsBetween(int $minDays, int $maxDays)
    {
        $now = now()->startOfDay();

        return UserContract::latestPerUserInCompany($this->companyId)
            ->filter(function ($c) use ($now, $minDays, $maxDays) {
                $days = $now->diffInDays($c->contract_end_date, false);

                return $days >= $minDays && $days < $maxDays;
            })
            ->values();
    }

    /** Buat modal baris "Akan Berakhir" - resolve daftar sesuai $activeModal yang lagi aktif. */
    public function getExpiringDetailProperty()
    {
        return match ($this->activeModal) {
            'kontrak-urgent' => $this->expiringContracts(30),
            'kontrak-attention' => $this->expiringContractsBetween(30, 90),
            'orientasi-urgent' => $this->expiringOrientations(7),
            default => collect(),
        };
    }

    public function render()
    {
        return view('livewire.dashboard.employee-type-breakdown-widget');
    }
}
