<?php

namespace App\Livewire\MyStaff;

use App\Livewire\MyStaff\Concerns\LazyLoadsOnExpand;
use App\Models\OrientationSubmission;
use App\Models\User\UserContract;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Kontrak / Orientasi Staff Akan Berakhir" di halaman MyStaff - pola sama
 * EmployeeTypeBreakdownWidget (Dashboard Anda), tapi scope SELALU staff
 * (OrgHierarchyService::subordinates()), bukan company.
 *
 * Data di-query pas tab "Dashboard" diklik pertama kali - lihat LazyLoadsOnExpand.
 */
class KontrakOrientasiTimWidget extends Component
{
    use LazyLoadsOnExpand;

    private function subordinateIds()
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');
    }

    /** Kontrak dari periode kontrak TERBARU per user (UserContract, ledger append-only), yang
     * berakhir dalam $maxDays hari ke depan - scope staff, bukan lewat
     * UserContract::latestPerUserInCompany() (itu company-wide). */
    private function expiringContracts(int $minDays, int $maxDays)
    {
        $now = now()->startOfDay();

        return UserContract::query()
            ->whereIn('user_id', $this->subordinateIds())
            ->whereNotNull('contract_end_date')
            ->with(['user.profile.jobPosition'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($group) => $group->sortByDesc('contract_start_date')->first())
            ->filter(function ($c) use ($now, $minDays, $maxDays) {
                $days = $now->diffInDays($c->contract_end_date, false);

                return $days >= $minDays && $days < $maxDays;
            })
            ->values();
    }

    private function expiringOrientations(int $maxDays)
    {
        $now = now()->startOfDay();

        return OrientationSubmission::query()
            ->whereIn('user_id', $this->subordinateIds())
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

    /** Dipanggil blade cuma pas $loaded true (lihat kontrak-orientasi-tim-widget.blade.php). */
    public function getRowsProperty(): array
    {
        return [
            ['key' => 'kontrak-urgent', 'count' => $this->expiringContracts(0, 30)->count(), 'type' => 'Kontrak', 'typeBadge' => 'badge-info', 'desc' => 'Berakhir < 30 hari', 'urgencyBadge' => 'badge-rejected', 'urgencyLabel' => 'Urgent', 'icon' => 'fa-file-contract', 'tile' => 'danger'],
            ['key' => 'orientasi-urgent', 'count' => $this->expiringOrientations(7)->count(), 'type' => 'Orientasi', 'typeBadge' => 'badge-pending', 'desc' => 'Berakhir < 7 hari', 'urgencyBadge' => 'badge-rejected', 'urgencyLabel' => 'Urgent', 'icon' => 'fa-user-clock', 'tile' => 'warn'],
            ['key' => 'kontrak-attention', 'count' => $this->expiringContracts(30, 90)->count(), 'type' => 'Kontrak', 'typeBadge' => 'badge-info', 'desc' => 'Berakhir 30–90 hari', 'urgencyBadge' => 'badge-pending', 'urgencyLabel' => 'Perhatian', 'icon' => 'fa-file-contract', 'tile' => 'warn'],
        ];
    }

    public ?string $activeModal = null;

    public function openModal(string $key): void
    {
        $this->activeModal = $key;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    public function getDetailProperty()
    {
        return match ($this->activeModal) {
            'kontrak-urgent' => $this->expiringContracts(0, 30),
            'kontrak-attention' => $this->expiringContracts(30, 90),
            'orientasi-urgent' => $this->expiringOrientations(7),
            default => collect(),
        };
    }

    public function render()
    {
        return view('livewire.my-staff.kontrak-orientasi-tim-widget');
    }
}
