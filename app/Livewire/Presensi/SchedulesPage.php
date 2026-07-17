<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman pertama restrukturisasi Jadwal (Registry+Strategy baru, ganti "Shift" lama — lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md). Cari/pagination ditangani List.js
 * di browser (lihat resources/js/app.js), bukan Livewire — semua jadwal 1 company dimuat
 * sekali. "Tambah Jadwal" mengarah ke halaman terpisah (CreateSchedulePage), bukan modal.
 */
#[Layout('components.layout', ['title' => 'Jadwal'])]
class SchedulesPage extends Component
{
    public ?string $companyId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    /** Company benar-benar ganti data dari database, jadi tetap lewat Livewire — List.js cuma
     * perlu diberitahu barisnya sudah diganti (lihat resources/js/app.js). */
    public function updatedCompanyId(): void
    {
        $this->dispatch('list-table:refresh');
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getSchedulesProperty()
    {
        return Schedule::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->with('orgStructure')
            ->orderBy('name')
            ->get();
    }

    public function scheduleTypeLabel(string $value): string
    {
        return ScheduleType::tryFrom($value)?->getLabel() ?? $value;
    }

    public function render()
    {
        return view('livewire.presensi.schedules-page');
    }
}
