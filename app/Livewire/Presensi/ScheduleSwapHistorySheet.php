<?php

namespace App\Livewire\Presensi;

use App\Livewire\Presensi\Concerns\FormatsShiftSwapRequest;
use App\Models\Shift\Schedule;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Riwayat tukar shift 1 JADWAL (bukan milik sendiri) - dibuka lewat ikon "tukar" emas di widget
 * "Jadwal Shift Tim" (Dashboard) & tab Penjadwalan halaman Detail Jadwal. Read-only, 30 baris
 * terbaru, semua status. Beda dari ShiftSwapHistorySheet yang scope-nya requester_id = user login.
 * Scope by reqSchedule->shiftType->schedule_id: 1 pengajuan tukar shift selalu antar rekan
 * sejadwal (target candidates di modal sudah difilter ke schedule yang sama), jadi cukup lihat
 * sisi pemohon.
 */
class ScheduleSwapHistorySheet extends Component
{
    use FormatsShiftSwapRequest;

    public ?string $scheduleId = null;

    public bool $showModal = false;

    public bool $showTrigger = true;

    /** Diisi kalau dibuka dari ikon "tukar" di 1 karyawan+tanggal tertentu - daftar dipersempit ke
     * pengajuan yang menyentuh (karyawan, tanggal) itu saja dan ditampilkan mendetail. */
    public ?string $filterUserId = null;

    public ?string $filterDate = null;

    /** Dibuka juga dari ikon "tukar" emas yang nempel di tiap sel/tanggal roster & baris tim
     * (host component dispatch 'open-schedule-swap-history' global, membawa userId & date). */
    #[On('open-schedule-swap-history')]
    public function openModal(?string $userId = null, ?string $date = null): void
    {
        $this->filterUserId = $userId;
        $this->filterDate = $userId && $date ? $date : null;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function getIsFilteredProperty(): bool
    {
        return (bool) ($this->filterUserId && $this->filterDate);
    }

    public function getFilteredUserNameProperty(): ?string
    {
        return $this->isFiltered ? User::find($this->filterUserId)?->name : null;
    }

    public function getRequestsProperty()
    {
        if (! $this->scheduleId) {
            return collect();
        }

        // Global scope company dibuka (dibatasi ke company jadwal ini): ShiftSwapRequests & baris
        // jadwal yang dirujuknya kesaring buat akun beda company, dan baris yang dihapus saat approved
        // (Double Shift) membuat whereHas reqSchedule kosong -> modal tidak menampilkan detail.
        $companyId = Schedule::whereKey($this->scheduleId)->value('company_id');

        $query = ShiftSwapRequests::withoutGlobalScope('company')->where('company_id', $companyId)
            ->with([
                'requester', 'target',
                'reqSchedule' => fn ($q) => $q->withoutGlobalScope('company')->with('shiftType'),
                'targetSchedule' => fn ($q) => $q->withoutGlobalScope('company')->with('shiftType'),
            ]);

        if (! $this->isFiltered) {
            $query->whereHas('reqSchedule', fn ($q) => $q->withoutGlobalScope('company')
                ->whereHas('shiftType', fn ($qq) => $qq->where('schedule_id', $this->scheduleId)));
        }

        if ($this->isFiltered) {
            $query->whereIn('status', ['in_review', 'approved'])
                ->where(function ($q) {
                    $q->where(fn ($qq) => $qq->where('requester_id', $this->filterUserId)->whereDate('req_date', $this->filterDate))
                        ->orWhere(fn ($qq) => $qq->where('target_id', $this->filterUserId)->whereDate('target_date', $this->filterDate));
                });
        }

        return $query->orderByDesc('created_at')->limit(30)->get();
    }

    public function render()
    {
        return view('livewire.presensi.schedule-swap-history-sheet');
    }
}
