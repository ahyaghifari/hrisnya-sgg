<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Services\Shift\ShiftSwapRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Modal "Tukar Shift" nempel di bawah widget "Jadwal Shift Saya" (Dashboard, lihat
 * resources/views/livewire/partials/home-cards.blade.php) - bukan halaman terpisah, instruksi
 * user 2026-08-16. Alur (2026-08-20, urutan final): pilih tanggal shift SENDIRI dulu (reqDate,
 * default besok, tidak bisa kurang dari hari ini) -> pilih mode tanggal sama/beda -> kalau beda,
 * isi dulu tanggal shift REKAN (targetDate) -> baru cari rekan (TomSelect), opsinya SUDAH
 * difilter ke yang punya jadwal shift persis di tanggal EFEKTIF itu (reqDate kalau mode sama,
 * targetDate kalau beda) - lihat getEffectiveTargetDateProperty()/getTargetCandidatesProperty().
 * Pencarian rekan DIBATASI ke sesama pemakai jadwal shift yang SAMA dengan user login (instruksi
 * user 2026-08-16) - lihat myScheduleId().
 * Submit lewat ShiftSwapRequestService::store() yang sudah ada - kompatibilitas tipe shift & (buat
 * tanggal beda) fallback Extra Shift kalau rekan/pemohon TERNYATA sudah punya jadwal lain di
 * tanggal tujuan, semua dicek di situ (lihat ShiftSwapRequestService::validateScheduleSwapRules /
 * executeApprovedSwap).
 */
class ShiftSwapRequestModal extends Component
{
    public bool $showModal = false;

    /** Tanggal shift SENDIRI yang mau ditukar - default besok, tidak boleh kurang dari hari ini.
     * Dipilih LEBIH DULU - daftar rekan di bawah baru muncul setelah ini terisi. */
    public string $reqDate = '';

    public ?string $targetUserId = null;

    /** 'same' = rekan ditukar di tanggal yang sama dengan reqDate, 'different' = rekan punya
     * tanggal shift sendiri (targetDate). */
    public string $dateMode = 'same';

    public ?string $targetDate = null;

    public ?string $notes = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    private function resetForm(): void
    {
        $this->reset(['targetUserId', 'targetDate', 'notes']);
        $this->reqDate = now()->addDay()->toDateString();
        $this->dateMode = 'same';
    }

    /** Ganti tanggal/mode -> rekan yang sebelumnya dipilih bisa jadi tidak lagi punya jadwal di
     * tanggal EFEKTIF yang baru, jadi direset biar tidak nyangkut kombinasi yang sudah tidak
     * relevan (rekan selalu dipilih PALING TERAKHIR, setelah tanggal efektifnya jelas). */
    public function updatedReqDate(): void
    {
        $this->targetUserId = null;
    }

    public function updatedDateMode(): void
    {
        $this->targetUserId = null;
        $this->targetDate = null;
    }

    public function updatedTargetDate(): void
    {
        $this->targetUserId = null;
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

    public function getEffectiveTargetDateProperty(): ?string
    {
        return $this->dateMode === 'different' ? $this->targetDate : $this->reqDate;
    }

    /** Rekan sejadwal yang PUNYA jadwal shift di tanggal EFEKTIF (reqDate kalau mode sama,
     * targetDate kalau beda) - dipilih lewat TomSelect, label langsung nampilin nama shift-nya
     * biar user tahu apa yang akan ditukar tanpa buka langkah berikutnya dulu. Baris
     * ScheduleShiftAssignment (bukan cuma keanggotaan roster) - satu baris per user per tanggal
     * (unique user_id+date) jadi otomatis 1 opsi per rekan. */
    public function getTargetCandidatesProperty()
    {
        if (! $this->effectiveTargetDate) {
            return collect();
        }

        $scheduleId = $this->myScheduleId();

        if (! $scheduleId) {
            return collect();
        }

        return ScheduleShiftAssignment::whereHas('shiftType', fn ($q) => $q->where('schedule_id', $scheduleId))
            ->whereDate('date', $this->effectiveTargetDate)
            ->whereNotNull('shift_type_id')
            ->where('user_id', '!=', Auth::id())
            ->with(['user', 'shiftType'])
            ->get()
            ->filter(fn ($row) => $row->user)
            ->sortBy('user.name')
            ->values();
    }

    /** Jadwal shift SENDIRI di reqDate - null kalau libur/tidak ada jadwal di tanggal itu. */
    public function getReqShiftProperty(): ?ScheduleShiftAssignment
    {
        if (! $this->reqDate) {
            return null;
        }

        return ScheduleShiftAssignment::where('user_id', Auth::id())
            ->whereDate('date', $this->reqDate)
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->first();
    }

    /** Jadwal shift REKAN di tanggal efektifnya - null kalau libur/tidak ada jadwal di tanggal
     * itu (dipakai blade buat kasih tahu user sebelum submit, service tetap validasi ulang). Buat
     * mode 'same' ini pasti ketemu (rekan sudah difilter dari targetCandidates), tapi tetap
     * di-resolve ulang di sini biar 1 sumber kebenaran sama dengan mode 'different'. */
    public function getTargetShiftProperty(): ?ScheduleShiftAssignment
    {
        if (! $this->targetUserId || ! $this->effectiveTargetDate) {
            return null;
        }

        return ScheduleShiftAssignment::where('user_id', $this->targetUserId)
            ->whereDate('date', $this->effectiveTargetDate)
            ->whereNotNull('shift_type_id')
            ->with(['shiftType', 'user'])
            ->first();
    }

    public function submit(): void
    {
        $today = now()->toDateString();

        $this->validate([
            'reqDate' => ['required', 'date', 'after_or_equal:' . $today],
            'targetUserId' => ['required'],
            'dateMode' => ['required', 'in:same,different'],
            'targetDate' => ['required_if:dateMode,different', 'nullable', 'date', 'after_or_equal:' . $today],
            'notes' => ['nullable', 'string'],
        ], [], [
            'reqDate' => 'tanggal shift Anda',
            'targetUserId' => 'rekan',
            'targetDate' => 'tanggal shift rekan',
        ]);

        $reqSchedule = $this->reqShift;
        if (! $reqSchedule) {
            $this->addError('reqDate', 'Anda tidak punya jadwal shift di tanggal ini.');

            return;
        }

        $targetSchedule = $this->targetShift;
        if (! $targetSchedule) {
            $this->addError('targetDate', 'Rekan tidak punya jadwal shift di tanggal ini (atau sedang libur).');

            return;
        }

        try {
            app(ShiftSwapRequestService::class)->store([
                'swap_mode' => 'schedule',
                'req_schedule_id' => $reqSchedule->id,
                'target_schedule_id' => $targetSchedule->id,
                'notes' => $this->notes,
            ]);

            Notification::make()
                ->title('Pengajuan tukar shift berhasil dikirim')
                ->success()
                ->send();

            $this->showModal = false;
            $this->resetForm();
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
