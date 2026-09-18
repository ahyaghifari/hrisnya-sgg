<?php

namespace App\Livewire\MyStaff;

use App\Livewire\MyStaff\Concerns\LazyLoadsOnExpand;
use App\Models\User;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Ringkasan Tim" di halaman MyStaff - total bawahan + breakdown status kepegawaian
 * (Tetap/Kontrak/Orientasi), scope SELALU bawahan (langsung + rekursif via
 * OrgHierarchyService::subordinates()), bukan company - beda dari widget serupa di Dashboard
 * Anda (EmployeeTypeBreakdownWidget) yang company-wide buat HC Manager.
 *
 * Datanya dipanggil blade cuma pas $loaded true (lihat LazyLoadsOnExpand + blade-nya sendiri
 * yang bungkus semua ini di dalam @if ($loaded)).
 *
 * Tile tipe kepegawaian bisa diklik buat lihat daftar bawahannya (modal) - pola sama
 * EmployeeTypeBreakdownWidget punya Dashboard Anda, biar setara kualitasnya (instruksi user
 * 2026-09-18: "diperbagus kaya dashboard hc manager").
 */
class RingkasanTimWidget extends Component
{
    use LazyLoadsOnExpand;

    public ?string $activeModal = null;

    public function openModal(string $typeName): void
    {
        $this->activeModal = $typeName;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    public function getBreakdownProperty()
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())
            ->whereHas('profile.employeeType')
            ->with('profile.employeeType')
            ->get()
            ->groupBy(fn (User $u) => $u->profile?->employeeType?->name ?? '-')
            ->map(fn ($group, $name) => ['name' => $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();
    }

    public function getTotalProperty(): int
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())->count();
    }

    /** Buat modal tile tipe kepegawaian - daftar bawahan dengan employeeType.name = $activeModal. */
    public function getEmployeesForTypeProperty()
    {
        if (! $this->activeModal) {
            return collect();
        }

        return app(OrgHierarchyService::class)->subordinates(Auth::user())
            ->whereHas('profile.employeeType', fn ($q) => $q->where('name', $this->activeModal))
            ->with('profile.jobPosition')
            ->get()
            ->sortBy('name')
            ->values();
    }

    public function colorForType(string $name): string
    {
        $lower = strtolower($name);

        return match (true) {
            str_contains($lower, 'tetap') => '#047C54',
            str_contains($lower, 'orientasi') => '#d97706',
            default => 'var(--brand)',
        };
    }

    public function render()
    {
        return view('livewire.my-staff.ringkasan-tim-widget');
    }
}
