<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\User;
use App\Services\Shift\ShiftSwapRequestService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Modal "Tukar Shift" nempel di bawah widget "Jadwal Shift Saya" (Dashboard, lihat
 * resources/views/livewire/partials/home-cards.blade.php) - bukan halaman terpisah, instruksi
 * user 2026-08-16. Alur SEDERHANA, tanpa pilihan "mode" (instruksi user 2026-08-16 - dulu ada 2
 * mode schedule/off_day yang bikin bingung, sekarang cuma jalur tukar 2 jadwal): cari rekan ->
 * pilih jadwal REKAN yang mau ditukar -> pilih jadwal SENDIRI penggantinya (harus bulan yang
 * sama - boleh tanggal sama atau beda, lihat ShiftSwapRequestService::validateScheduleSwapRules).
 * Pencarian rekan DIBATASI ke sesama pemakai jadwal shift yang SAMA dengan user login (instruksi
 * user 2026-08-16) - lihat getTargetCandidatesProperty()/myScheduleId().
 * Submit lewat ShiftSwapRequestService::store() yang sudah ada (backend dibangun 2026-08-14/15).
 */
class ShiftSwapRequestModal extends Component
{
    public bool $showModal = false;

    public string $targetSearch = '';

    public ?string $targetUserId = null;

    public ?string $targetScheduleId = null;

    public ?string $reqScheduleId = null;

    public ?string $notes = null;

    public function openModal(): void
    {
        $this->reset(['targetSearch', 'targetUserId', 'targetScheduleId', 'reqScheduleId', 'notes']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    /** Schedule (shift) yang lagi dipakai user login - sama pola
     * Dashboard::getMyScheduleIdProperty() (roster ScheduleShiftAssignment menang, fallback
     * ScheduleAssignment). Dipakai buat batasi pencarian rekan ke sesama pemakai jadwal ini. */
    private function myScheduleId(): ?string
    {
        $user = Auth::user();

        $shiftAssignment = ScheduleShiftAssignment::where('user_id', $user->id)
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->latest('date')
            ->first();

        if ($shiftAssignment?->shiftType) {
            return $shiftAssignment->shiftType->schedule_id;
        }

        return ScheduleAssignment::resolveForUser($user, now()->toDateString())?->schedule_id;
    }

    /** Cari rekan kerja by nama/NIP, exclude diri sendiri - pola sama App\Livewire\Presensi\
     * Concerns\HandlesShiftScheduleForm (employeeSearch). Cuma jalan kalau sudah ketik minimal
     * 2 karakter, biar tidak nge-load semua karyawan pas modal baru dibuka. Dibatasi ke rekan
     * yang PAKAI JADWAL SHIFT YANG SAMA (instruksi user 2026-08-16, ganti dari batasan divisi
     * sebelumnya) - tukar shift cuma masuk akal sesama pemakai 1 jadwal/roster, company_id ikut
     * lewat relasi shiftType->schedule (Schedule cuma dipakai 1 company). User yang jadwal
     * shift-nya tidak ketemu (belum di-assign) sengaja dapat hasil kosong. */
    public function getTargetCandidatesProperty()
    {
        if (mb_strlen(trim($this->targetSearch)) < 2) {
            return collect();
        }

        $scheduleId = $this->myScheduleId();

        if (! $scheduleId) {
            return collect();
        }

        $candidateIds = ScheduleShiftAssignment::whereHas('shiftType', fn ($q) => $q->where('schedule_id', $scheduleId))
            ->where('user_id', '!=', Auth::id())
            ->distinct()
            ->pluck('user_id');

        return User::whereIn('id', $candidateIds)
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->targetSearch}%")
                    ->orWhere('nip', 'like', "%{$this->targetSearch}%");
            })
            ->orderBy('name')
            ->limit(15)
            ->get();
    }

    public function selectTarget(string $userId): void
    {
        $this->targetUserId = $userId;
        $this->targetSearch = '';
        $this->targetScheduleId = null;
        $this->reqScheduleId = null;
    }

    public function clearTarget(): void
    {
        $this->targetUserId = null;
        $this->targetScheduleId = null;
        $this->reqScheduleId = null;
    }

    /** Jadwal shift REKAN, tanggal >= hari ini - dipilih LEBIH DULU (instruksi user), baru
     * setelah ini jadwal sendiri difilter ke bulan yang sama. */
    public function getTargetShiftsProperty()
    {
        if (! $this->targetUserId) {
            return collect();
        }

        return ScheduleShiftAssignment::where('user_id', $this->targetUserId)
            ->where('date', '>=', now()->toDateString())
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->orderBy('date')
            ->get();
    }

    /** Ganti jadwal rekan -> reset pilihan jadwal sendiri, biar tidak nyangkut kombinasi lama
     * yang bulannya sudah tidak relevan. */
    public function updatedTargetScheduleId(): void
    {
        $this->reqScheduleId = null;
    }

    /** Jadwal SENDIRI, difilter ke BULAN YANG SAMA dengan jadwal rekan yang sudah dipilih -
     * ShiftSwapRequestService::validateScheduleSwapRules() cuma izinkan tukar dalam bulan yang
     * sama (boleh tanggal sama atau beda), jadi opsi di luar itu percuma ditawarkan. */
    public function getMyShiftsProperty()
    {
        if (! $this->targetScheduleId) {
            return collect();
        }

        $targetShift = ScheduleShiftAssignment::find($this->targetScheduleId);
        if (! $targetShift) {
            return collect();
        }

        $monthStart = Carbon::parse($targetShift->date)->startOfMonth();
        $monthEnd = Carbon::parse($targetShift->date)->endOfMonth();

        return ScheduleShiftAssignment::where('user_id', Auth::id())
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->orderBy('date')
            ->get();
    }

    public function submit(): void
    {
        $this->validate([
            'targetUserId' => ['required'],
            'targetScheduleId' => ['required'],
            'reqScheduleId' => ['required'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            app(ShiftSwapRequestService::class)->store([
                'swap_mode' => 'schedule',
                'req_schedule_id' => $this->reqScheduleId,
                'target_schedule_id' => $this->targetScheduleId,
                'notes' => $this->notes,
            ]);

            Notification::make()
                ->title('Pengajuan tukar shift berhasil dikirim')
                ->success()
                ->send();

            $this->showModal = false;
            $this->reset(['targetSearch', 'targetUserId', 'targetScheduleId', 'reqScheduleId', 'notes']);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal mengajukan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.presensi.shift-swap-request-modal');
    }
}
