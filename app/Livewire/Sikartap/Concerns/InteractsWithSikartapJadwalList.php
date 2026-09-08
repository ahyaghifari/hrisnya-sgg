<?php

namespace App\Livewire\Sikartap\Concerns;

use App\Models\Master\Companies;
use App\Models\Sikartap\Sikartap;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * Kerangka halaman daftar per-tahap (TesPage / WawancaraPage): filter company + cari,
 * pilih massal (checkbox), state modal, + helper penjadwalan massal. Aturan bisnisnya
 * (gate/validasi/notifikasi) datang dari ManagesSikartapTesWawancara yang dipakai bareng.
 *
 * Host wajib meng-implement tahapQuery() (siapa yang tampil di tahap ini).
 */
trait InteractsWithSikartapJadwalList
{
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** Panel modal aktif: schedule | result | null (nama panel spesifik di masing-masing page). */
    public ?string $activePanel = null;

    /** @var array<int, string> ID Sikartap yang dicentang untuk penjadwalan massal. */
    public array $selected = [];

    /** Baris yang sedang diisi hasilnya (modal per-baris). */
    public ?string $resultId = null;

    abstract protected function tahapQuery();

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:Sikartap'), 403);

        if (Auth::user()->canFilterByCompany() && $this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCanUpdateProperty(): bool
    {
        return Auth::user()->can('Update:Sikartap');
    }

    protected function sikartapContext(): Sikartap
    {
        return Sikartap::with(['user', 'testSchedule', 'interviewSchedule'])->findOrFail($this->resultId);
    }

    public function updatedCompanyId(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function closeModal(): void
    {
        $this->activePanel = null;
        $this->resultId = null;
        $this->resetErrorBag();
    }

    /**
     * Terapkan 1 form jadwal ke semua baris terpilih yang lolos $gate. Return jumlah yang
     * benar-benar dijadwalkan (baris yang tidak lolos tahap diam-diam dilewati).
     */
    protected function applyScheduleToSelected(\Closure $gate, array $rules, \Closure $apply, string $label): void
    {
        abort_unless($this->canUpdate, 403);

        $this->validate($rules);

        $targets = Sikartap::whereIn('id', $this->selected)
            ->with(['user', 'testSchedule', 'interviewSchedule'])
            ->get()
            ->filter($gate);

        foreach ($targets as $sikartap) {
            $apply($sikartap);
        }

        $applied = $targets->count();
        $this->selected = [];
        $this->activePanel = null;

        Notification::make()
            ->title($applied > 0 ? "{$label} disimpan untuk {$applied} orang" : 'Tidak ada yang bisa dijadwalkan pada tahap ini')
            ->{$applied > 0 ? 'success' : 'warning'}()
            ->send();
    }

    protected function baseQuery()
    {
        $query = $this->tahapQuery()->with(['user.company', 'testSchedule', 'interviewSchedule']);

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

    public function getJadwalProperty()
    {
        return $this->baseQuery()
            ->latest()
            ->paginate(10)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search]);
    }
}
