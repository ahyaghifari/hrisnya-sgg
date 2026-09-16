<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftTeamMember;
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
 * Submit lewat ShiftSwapRequestService::store() yang sudah ada - kompatibilitas tipe shift dicek
 * di situ (lihat ShiftSwapRequestService::validateScheduleSwapRules). Buat tanggal beda, kalau
 * rekan/pemohon TERNYATA sudah punya jadwal lain di tanggal tujuan, shift itu TIDAK dipindah
 * otomatis (2026-09-12, dulu fallback Extra Shift) - cuma dikasih peringatan double shift/nyambung
 * shift, lihat getConflictWarningsProperty()/ShiftSwapRequestService::conflictWarningsForSchedules().
 *
 * Mode ke-3 "reciprocal" (2026-09-12, lihat planning/2026-09-12-tukar-shift-libur-berpasangan.md):
 * tukar libur berpasangan - rekan sedang LIBUR BENERAN (off_day=true, bukan N/A/kosong) di
 * reqDate, pemohon WAJIB "membayar" pakai salah satu tanggal LIBURNYA SENDIRI (off_day=true juga)
 * untuk mengambil alih 1 shift kerja rekan di tanggal itu sebagai kompensasi (instruksi user
 * 2026-09-14 - bukan sekadar tanggal kosong mana pun yang kebetulan tidak bentrok, dan bukan
 * gratisan seperti storeOffDay() lama yang sudah dihapus). Beda dari 2 mode lain: rekan dipilih
 * DULU (getOffDayCandidatesProperty, dipersempit ke sesama Regu kalau jadwalnya pakai Regu -
 * instruksi user 2026-09-16), baru tanggal kompensasi (getCompensationCandidatesProperty - irisan
 * shift kerja rekan dengan tanggal Libur pemohon sendiri). Konflik nyata di mode ini diblok KERAS
 * oleh service (bukan warning-only, tetap dipertahankan begitu - instruksi user 2026-09-16),
 * jadi conflictWarnings tidak dipakai untuk mode ini.
 */
class ShiftSwapRequestModal extends Component
{
    public bool $showModal = false;

    /** Tanggal shift SENDIRI yang mau ditukar - default besok, tidak boleh kurang dari hari ini.
     * Dipilih LEBIH DULU - daftar rekan di bawah baru muncul setelah ini terisi. */
    public string $reqDate = '';

    public ?string $targetUserId = null;

    /** 'same' = rekan ditukar di tanggal yang sama dengan reqDate, 'different' = rekan punya
     * tanggal shift sendiri (targetDate), 'reciprocal' = rekan sedang LIBUR di reqDate -
     * pemohon wajib "membayar" balik dengan mengambil alih shift kerja rekan di tanggal lain
     * (targetDate), lihat planning/2026-09-12-tukar-shift-libur-berpasangan.md. Beda urutan
     * pilih dari 2 mode lain: rekan dipilih DULU (dari yang libur di reqDate), baru tanggal
     * kompensasi (dari shift kerja rekan itu). */
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
        $this->reqDate = $this->minDate;
        $this->dateMode = 'same';
    }

    /** Tanggal paling awal yang boleh dipilih & default reqDate - hari ini, tidak boleh
     * sebelumnya. Jarak minimal hari pengajuan (dulu diatur lewat Pengaturan Absensi) SENGAJA
     * dimatikan total (instruksi user 2026-09-16). */
    public function getMinDateProperty(): string
    {
        return now()->toDateString();
    }

    /** Saldo tukar shift requester untuk periode absensi yang memuat reqDate - null kalau tidak
     * dibatasi. Ditampilkan sebagai info/peringatan di modal (service tetap re-validasi saat submit). */
    public function getSwapQuotaProperty(): ?array
    {
        $anchor = $this->reqDate
            ? \Illuminate\Support\Carbon::parse($this->reqDate)
            : now();

        return app(ShiftSwapRequestService::class)->swapQuota(Auth::user(), $anchor);
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

    /** Mode 'reciprocal' doang: ganti rekan -> tanggal kompensasi yang sebelumnya dipilih bisa
     * jadi bukan milik rekan baru, reset. Mode lain tidak butuh ini (rekan dipilih SETELAH
     * tanggal, lihat updatedTargetDate()). */
    public function updatedTargetUserId(): void
    {
        if ($this->dateMode === 'reciprocal') {
            $this->targetDate = null;
        }
    }

    public function updatedTargetDate(): void
    {
        // Mode 'reciprocal' urutannya kebalik dari 'different': rekan dipilih DULU, baru
        // tanggal kompensasi - ganti tanggal kompensasi TIDAK boleh menghapus rekan yang sudah
        // dipilih (beda dari 'different', di situ targetDate dipilih duluan sebelum rekan).
        if ($this->dateMode !== 'reciprocal') {
            $this->targetUserId = null;
        }
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
        return in_array($this->dateMode, ['different', 'reciprocal'], true) ? $this->targetDate : $this->reqDate;
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

    /** Rekan sejadwal yang LIBUR BENERAN di reqDate (baris ada, off_day=true) - dipakai mode
     * 'reciprocal'. N/A/kosong (tidak ada baris sama sekali) SENGAJA tidak muncul di sini -
     * beda dari getTargetCandidatesProperty() yang justru cari yang KERJA. Query langsung ke
     * ScheduleShiftAssignment (bukan ScheduleResolver) - lihat
     * ShiftSwapRequestService::assertReciprocalOffEligible().
     *
     * Kalau jadwal ini pakai Regu (Schedule::use_teams) dan PEMOHON sendiri sudah py regu di
     * bulan reqDate, dipersempit lagi ke sesama anggota regu itu (instruksi user 2026-09-16:
     * "pilih rekan berdasarkan regunya saja jika ada") - rekan sejadwal tapi beda regu/rotasi
     * biasanya memang tidak relevan buat tukar libur. Regu tidak aktif / pemohon belum py regu
     * bulan itu -> fallback ke sesama jadwal seperti biasa (tidak dipersempit). */
    public function getOffDayCandidatesProperty()
    {
        if (! $this->reqDate) {
            return collect();
        }

        $scheduleId = $this->myScheduleId();

        if (! $scheduleId) {
            return collect();
        }

        $sameScheduleUserIds = ScheduleShiftAssignment::whereHas('shiftType', fn ($q) => $q->where('schedule_id', $scheduleId))
            ->whereNotNull('shift_type_id')
            ->distinct()
            ->pluck('user_id');

        $period = \Illuminate\Support\Carbon::parse($this->reqDate)->format('Y-m');
        $myTeamId = Schedule::find($scheduleId)?->use_teams
            ? ScheduleShiftTeamMember::forPeriod($scheduleId, Auth::id(), $period)?->team_id
            : null;

        if ($myTeamId) {
            $teammateIds = ScheduleShiftTeamMember::where('schedule_id', $scheduleId)
                ->where('period', $period)
                ->where('team_id', $myTeamId)
                ->pluck('user_id');

            $sameScheduleUserIds = $sameScheduleUserIds->intersect($teammateIds);
        }

        return ScheduleShiftAssignment::where('off_day', true)
            ->whereDate('date', $this->reqDate)
            ->where('user_id', '!=', Auth::id())
            ->whereIn('user_id', $sameScheduleUserIds)
            ->with('user')
            ->get()
            ->filter(fn ($row) => $row->user)
            ->sortBy('user.name')
            ->values();
    }

    /** Shift KERJA rekan (targetUserId) yang bisa diambil alih sebagai kompensasi - dipakai
     * mode 'reciprocal', muncul setelah rekan dipilih. Dibatasi ke tanggal yang JUGA Libur
     * pemohon sendiri (off_day=true) - instruksi user 2026-09-14: pemohon "membayar" pakai jatah
     * liburnya sendiri, bukan tanggal kosong mana pun. Tanggal reqDate sendiri dikecualikan
     * (tidak boleh jadi kompensasi buat dirinya sendiri, lihat
     * ShiftSwapRequestService::assertReciprocalOffEligible()). */
    public function getCompensationCandidatesProperty()
    {
        if (! $this->targetUserId) {
            return collect();
        }

        $ownOffDates = ScheduleShiftAssignment::where('user_id', Auth::id())
            ->where('off_day', true)
            ->where('date', '>=', $this->minDate)
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString());

        if ($ownOffDates->isEmpty()) {
            return collect();
        }

        return ScheduleShiftAssignment::where('user_id', $this->targetUserId)
            ->where('off_day', false)
            ->whereNotNull('shift_type_id')
            ->whereIn('date', $ownOffDates)
            ->when($this->reqDate, fn ($q) => $q->whereDate('date', '!=', $this->reqDate))
            ->with('shiftType')
            ->orderBy('date')
            ->get();
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

    /** Preview peringatan double shift/nyambung shift SEBELUM submit (tanggal beda + salah satu
     * pihak sudah punya jadwal lain di tanggal tujuan) - service tetap cek ulang live di halaman
     * detail (jadwal bisa berubah antara submit & approval final), lihat
     * ShiftSwapRequestService::conflictWarningsForSchedules(). Tidak memblokir submit, murni info. */
    public function getConflictWarningsProperty(): array
    {
        // Mode 'reciprocal' tidak pakai warning-only - konflik nyata di mode ini diblok keras
        // saat submit (ShiftSwapRequestService::assertReciprocalOffEligible() + resolveSlot
        // strict), jadi banner ini tidak relevan ditampilkan di sana.
        if ($this->dateMode === 'reciprocal') {
            return [];
        }

        return app(ShiftSwapRequestService::class)->conflictWarningsForSchedules($this->reqShift, $this->targetShift);
    }

    /** Blokir keras (bukan sekadar warning) buat kasus shift yang mau dioper PERSIS SAMA tipenya
     * dengan shift yang sudah dipunya rekan/pemohon di tanggal tujuan - lihat
     * ShiftSwapRequestService::duplicateShiftBlock(). Dipakai blade buat nampilin keterangan &
     * nge-disable tombol Ajukan SEBELUM user coba submit (instruksi user 2026-09-16: "bisa
     * pilih, tapi ngga bisa submit, kasih keterangan"); service tetap validasi ulang keras saat
     * submit (defense-in-depth kalau jadwal berubah di antara render & submit). Berlaku di SEMUA
     * mode tanggal-beda (termasuk 'reciprocal' - service-nya sama-sama lewat resolveSlot()). */
    public function getDuplicateShiftBlockProperty(): ?string
    {
        return app(ShiftSwapRequestService::class)->duplicateShiftBlock($this->reqShift, $this->targetShift);
    }

    public function submit(): void
    {
        $minDate = $this->minDate;

        $this->validate([
            'reqDate' => ['required', 'date', 'after_or_equal:' . $minDate],
            'targetUserId' => ['required'],
            'dateMode' => ['required', 'in:same,different,reciprocal'],
            'targetDate' => ['required_if:dateMode,different', 'required_if:dateMode,reciprocal', 'nullable', 'date', 'after_or_equal:' . $minDate],
            'notes' => ['nullable', 'string'],
        ], [], [
            'reqDate' => 'tanggal shift Anda',
            'targetUserId' => 'rekan',
            'targetDate' => $this->dateMode === 'reciprocal' ? 'tanggal kompensasi' : 'tanggal shift rekan',
        ]);

        $reqSchedule = $this->reqShift;
        if (! $reqSchedule) {
            $this->addError('reqDate', 'Anda tidak punya jadwal shift di tanggal ini.');

            return;
        }

        $targetSchedule = $this->targetShift;
        if (! $targetSchedule) {
            $message = $this->dateMode === 'reciprocal'
                ? 'Pilih tanggal shift kerja rekan yang mau Anda ambil alih sebagai kompensasi.'
                : 'Rekan tidak punya jadwal shift di tanggal ini (atau sedang libur).';
            $this->addError('targetDate', $message);

            return;
        }

        try {
            app(ShiftSwapRequestService::class)->store([
                'swap_kind' => $this->dateMode === 'reciprocal' ? 'reciprocal_off' : 'schedule',
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
