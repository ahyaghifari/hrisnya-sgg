<?php

namespace App\Livewire\Sikartap;

use App\Models\Master\Companies;
use App\Models\Sikartap\Sikartap;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Antrian MCU — daftar pengajuan Sikartap yang lagi nunggu input MCU, dengan tombol per
 * stasiun (perawat/lab/radiologi/dokter) sesuai permission staff yang login. Pengganti 4
 * dashboard staff legacy yang disatukan (dashboard_staff_lab.php dst).
 *
 * Dulu pernah dibubarkan ke DashboardSikartapPage (2026-08-25/26), dibikin ulang sebagai
 * halaman terpisah 2026-08-28 (instruksi user: dropdown Sikartap dibagi lagi). Logika
 * "aksi MCU apa yang tersedia per baris" TIDAK diduplikasi — pakai Sikartap::mcuActionsFor().
 *
 * Eligibility (requiresMcu/ideInovasiApproved) baca Setting per-company, bukan kolom, jadi
 * filter final + paginasi dilakukan di PHP (bukan SQL LIMIT/OFFSET) — sama pola dashboard.
 */
#[Layout('components.layout', ['title' => 'Antrian MCU'])]
class McuQueuePage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->canInputSikartapMcu() || $user->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value),
            403,
        );

        if ($user->canFilterByCompany() && $this->companyId === null) {
            $this->companyId = $user->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    protected function baseQuery()
    {
        $query = Sikartap::query()
            ->with(['user.company', 'mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination'])
            ->where('status_wawancara', 'disarankan')
            ->where('status_mcu', 'pending');

        if (Auth::user()->canFilterByCompany()) {
            if ($this->companyId) {
                $query->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId));
            }
        } else {
            $query->whereHas('user', fn ($q) => $q->where('company_id', Auth::user()->company_id));
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%"));
        }

        return $query;
    }

    public function getAntrianProperty(): LengthAwarePaginator
    {
        $user = Auth::user();

        $eligible = $this->baseQuery()->latest()->get()
            ->filter(fn (Sikartap $s) => count($s->mcuActionsFor($user)) > 0)
            ->values();

        $page = Paginator::resolveCurrentPage('page');
        $appends = ['companyId' => $this->companyId, 'search' => $this->search];

        return new LengthAwarePaginator(
            $eligible->forPage($page, 10)->values(),
            $eligible->count(),
            10,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $appends],
        );
    }

    public function render()
    {
        return view('livewire.sikartap.mcu-queue-page', [
            'canFilterByCompany' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
