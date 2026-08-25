<?php

namespace App\Livewire\Sikartap;

use App\Models\Master\Companies;
use App\Models\Sikartap\Sikartap;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal daftar jadwal Tes & Wawancara (belum ada sebelumnya - jadwal cuma bisa dilihat/diatur
 * lewat form per-record di PengajuanDetailPage, tidak ada satu tempat buat lihat semua jadwal
 * sekaligus). MCU SENGAJA tidak ikut di sini - lihat § Fase 3 rencana-implementasi-sikartap.md,
 * MCU tidak punya tabel jadwal terpisah (gate cukup status_wawancara=disarankan, staff klinis
 * isi kapan saja tanpa booking slot eksplisit).
 *
 * Halaman ini READ + LINK ke aksi, bukan tempat baru buat edit jadwal - form jadwalkan/ubah
 * tetap satu-satunya sumber kebenaran di PengajuanDetailPage (hindari 2 tempat yang bisa
 * menyimpang).
 */
#[Layout('components.layout', ['title' => 'Jadwal Tes & Wawancara'])]
class JadwalPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:Sikartap'), 403);

        if (Auth::user()->canFilterByCompany() && $this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
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
            ->with(['user.company', 'testSchedule', 'interviewSchedule'])
            ->where(fn ($q) => $q->whereHas('testSchedule')->orWhereHas('interviewSchedule'));

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
            ->appends([
                'companyId' => $this->companyId,
                'search' => $this->search,
            ]);
    }

    public function render()
    {
        return view('livewire.sikartap.jadwal-page', [
            'canFilterByCompany' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
