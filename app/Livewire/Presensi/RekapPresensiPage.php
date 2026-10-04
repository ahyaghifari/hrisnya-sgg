<?php

namespace App\Livewire\Presensi;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Concerns\FiltersByDepartmentDivision;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Services\Attendance\AttendanceResyncService;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use App\Services\Attendance\ScheduleResolver;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rekap presensi portal - padanan /admin/attendance-records?type=report tapi dengan 2 mode:
 * "harian" (data attendance_records hari ini apa adanya, per baris) dan "bulanan" (agregat per
 * karyawan 1 periode, mirror App\Filament\...\AttendanceReport::applyAttendanceMetrics() tapi
 * query di-parameterize pakai selectRaw(..., $bindings) - bukan string-interpolate raw SQL
 * seperti versi admin - dan kolom dipangkas ke yang penting saja (tanpa early_leave/overtime
 * aktual toggleable).
 *
 * Mode "bulanan" pakai window PERIODE (date_from/date_to bebas pilih), default-nya ikut cutoff
 * company (AttendancePeriodResolver, sama seperti Rekap Izin - IzinRecapPage) - BUKAN bulan
 * kalender 1-akhir bulan seperti sebelumnya, supaya konsisten dengan definisi "periode" yang
 * dipakai di seluruh modul absensi/payroll (2026-08-05).
 */
#[Layout('components.layout', ['title' => 'Rekap Presensi'])]
class RekapPresensiPage extends Component
{
    use WithPagination, FiltersByDepartmentDivision;

    #[Url]
    public string $mode = 'harian';

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** Filter daftar mode harian dari kartu ringkasan: '' = semua, atau present/late/permit/leave/sick/not_recorded.
     * #[Url] wajib: link paginasi (livewire.partials.pagination) memakai wire:navigate ke URL baru, jadi filter yang
     * tidak ada di query string hilang saat pindah halaman (dilaporkan user 2026-09-22). */
    #[Url]
    public string $statusFilter = '';

    /** Filter tipe jadwal karyawan (mode harian & periode): '' = semua, atau fixed / flexible / shift. */
    #[Url]
    public string $scheduleFilter = '';

    #[Url]
    public ?string $date_from = null;

    #[Url]
    public ?string $date_to = null;

    /** Tanggal yang ditampilkan di mode harian (tab "Harian"), format Y-m-d - default hari ini, tidak boleh
     * lebih dari hari ini. Lihat harianDay(). */
    #[Url(as: 'tanggal')]
    public ?string $harianDate = null;

    /** Tanggal yang ditampilkan di tab "Kehadiran" - default hari ini, sama aturan dengan
     * $harianDate (tidak boleh lebih dari hari ini) tapi state terpisah karena tab-nya beda. */
    #[Url(as: 'tanggal_kehadiran')]
    public ?string $kehadiranDate = null;

    /** Filter daftar tab "Kehadiran" dari kartu ringkasan: '' = semua yang terjadwal,
     * atau hadir/izin_cuti_sakit/belum_presensi. Sama pola dengan $statusFilter (mode harian). */
    #[Url]
    public string $kehadiranFilter = '';

    public bool $showSyncModal = false;

    public string $syncDate = '';

    public string $syncFromTime = '06:00';

    public ?string $syncResultMessage = null;

    public ?string $syncResultType = null;

    public array $syncNewRecords = [];

    /** Karyawan yang lagi dibuka detail hariannya (modal, mode bulanan) - null = modal tertutup. */
    public ?string $selectedUserId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value), 403);

        // Admin (bukan Super Admin) TETAP dikunci ke company sendiri, apapun isi companyId
        // dari query string (proteksi, bukan cuma default). Super Admin boleh lihat semua
        // perusahaan, tapi default awal (belum ada companyId di query string) diarahkan ke
        // company dia sendiri dulu - bukan langsung nampilin semua company sekaligus.
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }

        // Default periode bulanan ikut cutoff company (AttendancePeriodResolver), sama seperti
        // Rekap Izin - konsisten dengan periode absensi/kuota izin, bukan bulan kalender polos.
        // Cuma di-default kalau belum ada di query string (mis. dari link "Bulanan" halaman lain).
        $this->harianDate = $this->sanitizeHarianDate($this->harianDate);
        $this->kehadiranDate = $this->sanitizeHarianDate($this->kehadiranDate);

        if ($this->date_from === null || $this->date_to === null) {
            [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve(now(), $this->companyId);
            $this->date_from = $periodStart->toDateString();
            $this->date_to = $periodEnd->toDateString();
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->departmentId = null;
        $this->divisionId = null;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Tanggal valid (Y-m-d) dan tidak melewati hari ini, selain itu jatuh ke hari ini (query string bisa dioprek). */
    private function sanitizeHarianDate(?string $date): string
    {
        $today = now()->toDateString();

        if (! $date || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return $today;
        }

        return min($date, $today);
    }

    /** Tanggal aktif mode harian (selalu terisi, default hari ini). */
    protected function harianDay(): string
    {
        return $this->harianDate = $this->sanitizeHarianDate($this->harianDate);
    }

    public function updatedHarianDate(): void
    {
        $this->harianDate = $this->sanitizeHarianDate($this->harianDate);
        $this->resetPage();
    }

    public function resetHarianDate(): void
    {
        $this->harianDate = now()->toDateString();
        $this->resetPage();
    }

    /** Tanggal aktif tab Kehadiran (selalu terisi, default hari ini). */
    protected function kehadiranDay(): string
    {
        return $this->kehadiranDate = $this->sanitizeHarianDate($this->kehadiranDate);
    }

    public function updatedKehadiranDate(): void
    {
        $this->kehadiranDate = $this->sanitizeHarianDate($this->kehadiranDate);
        $this->resetPage();
    }

    public function resetKehadiranDate(): void
    {
        $this->kehadiranDate = now()->toDateString();
        $this->resetPage();
    }

    public function setKehadiranFilter(string $key): void
    {
        $allowed = ['hadir', 'izin_cuti_sakit', 'belum_presensi'];
        $this->kehadiranFilter = ($key === $this->kehadiranFilter || ! in_array($key, $allowed, true)) ? '' : $key;
        $this->resetPage();
    }

    public function getCanSyncProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::SYNC_DATA_ABSENSI->value);
    }

    public function openSyncModal(): void
    {
        abort_unless($this->canSync, 403);

        $this->syncDate = $this->harianDay();
        $this->syncFromTime = '08:00';
        $this->syncResultMessage = null;
        $this->syncResultType = null;
        $this->syncNewRecords = [];
        $this->showSyncModal = true;
    }

    public function closeSyncModal(): void
    {
        $this->showSyncModal = false;
    }

    /**
     * Sync manual dari access_logs untuk 1 tanggal, cuma scan >= jam tertentu (mis. abaikan
     * scan test/salah sebelum device benar2 online) - beda dari SyncAccessLogsAttendance
     * (cron incremental tiap menit, semua jam). Reuse pipeline yang sama
     * (AccessLogScheduleReader -> Calculators -> AttendanceRecordRepository, lihat
     * App\Console\Commands\ProcessAccessLogsSchedule --save), tanpa log ke channel
     * attendance_sync (audit trail bukan concern utama tool koreksi manual ini).
     *
     * "Baru" = baris attendance_records yang benar2 baru diinsert (wasRecentlyCreated),
     * BUKAN baris lama yang cuma keupdate ulang - hanya yang baru yang dikirimi notifikasi
     * push (AttendanceCheckedIn/AttendanceCheckedOut, sama seperti pipeline otomatis), supaya
     * re-sync tanggal yang sama tidak spam notifikasi berkali-kali ke karyawan yang sama.
     */
    public function runSync(
        AccessLogScheduleReader $reader,
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        ShiftScheduleCalculator $shiftCalculator,
        AttendanceRecordRepository $repo,
    ): void {
        abort_unless($this->canSync, 403);

        $this->validate([
            'syncDate' => ['required', 'date'],
            'syncFromTime' => ['required', 'date_format:H:i'],
        ], [], [
            'syncDate' => 'Tanggal',
            'syncFromTime' => 'Dari Jam',
        ]);

        $result = $reader->readAndGroup($this->syncDate, $this->syncDate, null, $this->syncFromTime . ':00');

        // Admin yang dibatasi 1 company (companyId ke-set otomatis, tidak bisa browse semua)
        // cuma boleh nyimpen hasil sync buat karyawan company-nya sendiri, biarpun scan-nya
        // lolos tahap validasi device di reader.
        $companyId = $this->companyId;
        $filterCompany = fn (array $entries) => $companyId
            ? array_values(array_filter($entries, fn ($e) => $e['user']->company_id === $companyId))
            : $entries;

        $newRecords = [];
        $fixedCount = 0;
        foreach ($fixedCalculator->calculate($filterCompany($result['buckets']['fixed'])) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_fixed');
            $this->collectIfNew($record, $r['user'], $newRecords);
            $fixedCount++;
        }

        $flexibleCount = 0;
        foreach ($flexibleCalculator->calculate($filterCompany($result['buckets']['flexible'])) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_flexible');
            $this->collectIfNew($record, $r['user'], $newRecords);
            $flexibleCount++;
        }

        $shiftCount = 0;
        foreach ($shiftCalculator->calculate($filterCompany($result['buckets']['shift'])) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_shift');
            $this->collectIfNew($record, $r['user'], $newRecords);
            $shiftCount++;
        }

        $this->syncNewRecords = $newRecords;
        $this->syncResultType = 'success';
        $this->syncResultMessage = "Sync selesai: {$fixedCount} jadwal tetap, {$flexibleCount} jadwal fleksibel, {$shiftCount} jadwal shift diproses, "
            . count($newRecords) . ' presensi baru tercatat.';
    }

    /**
     * @param  array<int, array{name: string, nip: string, check_in_time: ?string, check_out_time: ?string}>  &$newRecords
     */
    private function collectIfNew(AttendanceRecords $record, User $user, array &$newRecords): void
    {
        if (! $record->wasRecentlyCreated) {
            return;
        }

        $newRecords[] = [
            'name' => $user->name,
            'nip' => $user->nip,
            'check_in_time' => $record->check_in_time,
            'check_out_time' => $record->check_out_time,
        ];

        if ($record->check_in_time) {
            $user->notify(new AttendanceCheckedIn($record));
        }

        if ($record->check_out_time) {
            $user->notify(new AttendanceCheckedOut($record));
        }
    }

    /**
     * Betulkan baris attendance_records yang SUDAH ADA di periode terpilih (companyId ikut
     * kalau lagi dipilih) - 2 hal:
     *
     * 1. Status: kalau check_in_time DAN check_out_time DUA-DUANYA null (sama sekali tidak ada
     *    presensi), statusnya ditandai 'absent'. Baris yang masih punya salah satu (cuma
     *    check-in ATAU cuma check-out - mis. lupa scan satu sisinya) TETAP dianggap hadir, tidak
     *    ikut ditandai absent (instruksi user 2026-08-10). Cuti/Sakit/Izin yang disetujui (status
     *    leave/sick/permit) SENGAJA dikecualikan, biar tidak salah timpa jadi "mangkir" padahal
     *    memang tidak masuk kerja dengan alasan yang disetujui - keduanya juga wajar check_in/
     *    check_out-nya null.
     * 2. Jam kerja: buat SEMUA bars yang punya check_in_time DAN check_out_time, working_minutes
     *    dihitung ULANG dari durasi mentah check_out - check_in (jam istirahat IKUT terhitung
     *    sebagai jam kerja, tidak dipotong) - dikonfirmasi user 2026-08-05, timpa nilai lama
     *    kalau ada. Lintas tengah malam (check_out < check_in) dianggap nyambung ke hari
     *    berikutnya, sama pola dengan FlexibleScheduleCalculator.
     *
     * Tidak membuat baris baru untuk tanggal yang sama sekali tidak ada datanya (2026-08-05,
     * dikonfirmasi user).
     */
    public function syncPeriodAttendance(): void
    {
        abort_unless($this->canSync, 403);
        abort_unless($this->date_from && $this->date_to, 422);

        $excludedStatuses = [
            AttendanceStatus::LEAVE->value,
            AttendanceStatus::SICK->value,
            AttendanceStatus::PERMIT->value,
        ];

        $absentAffected = $this->scopedRecordsForRange($this->date_from, $this->date_to)
            ->whereNotIn('status', $excludedStatuses)
            ->whereNull('check_in_time')
            ->whereNull('check_out_time')
            ->update(['status' => AttendanceStatus::ABSENT->value]);

        $recordsToRecalculate = $this->scopedRecordsForRange($this->date_from, $this->date_to)
            ->whereNotNull('check_in_time')
            ->whereNotNull('check_out_time')
            ->get();

        foreach ($recordsToRecalculate as $record) {
            $ci = Carbon::createFromFormat('H:i:s', $record->check_in_time);
            $co = Carbon::createFromFormat('H:i:s', $record->check_out_time);

            if ($co->lt($ci)) {
                $co->addDay();
            }

            $record->update(['working_minutes' => (int) $ci->diffInMinutes($co)]);
        }

        Notification::make()
            ->title('Sync periode selesai')
            ->body("{$absentAffected} baris ditandai Tidak Hadir, {$recordsToRecalculate->count()} baris jam kerja dihitung ulang (istirahat ikut terhitung). Baris Cuti/Sakit/Izin tidak ikut diubah.")
            ->success()
            ->send();
    }

    /**
     * Hitung ulang working_minutes + late_minutes/early_leave_minutes/status SEMUA baris di
     * periode terpilih yang punya check-in & check-out, pakai rumus jadwal ASLI tiap karyawan
     * (FixedScheduleCalculator/FlexibleScheduleCalculator/ShiftScheduleCalculator via
     * ScheduleResolver, sama persis yang dipakai pipeline sync harian) - beda dari
     * syncPeriodAttendance() yang durasi mentah tanpa potong istirahat & tidak lewat calculator
     * jadwal sama sekali. Sebelumnya (2026-08-05) status/telat sengaja TIDAK disentuh tombol ini
     * (working_minutes-only, tombol terpisah) - digabung lagi 2026-08-12 (instruksi user: perlu
     * cara re-sync late_minutes pakai calculator terbaru tanpa generate ulang dari access_logs
     * mentah). Karyawan yang jadwalnya tidak ketemu/hari itu libur menurut jadwal dilewati (tidak
     * ada dasar hitung resminya).
     */
    public function recalculateWorkingHours(
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        ShiftScheduleCalculator $shiftCalculator,
        ScheduleResolver $resolver,
    ): void {
        abort_unless($this->canSync, 403);
        abort_unless($this->date_from && $this->date_to, 422);

        $records = $this->scopedRecordsForRange($this->date_from, $this->date_to)
            ->whereNotNull('check_in_time')
            ->whereNotNull('check_out_time')
            ->with('user')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! $record->user) {
                $skipped++;

                continue;
            }

            $resolved = $resolver->resolveForUserDate($record->user, $record->date);

            if (! $resolved || ($resolved['is_off_day'] ?? false) || ! isset($resolved['config'])) {
                $skipped++;

                continue;
            }

            $entry = [
                'check_in' => $record->check_in_time,
                'check_out' => $record->check_out_time,
                'config' => $resolved['config'],
                'schedule' => $resolved['schedule'] ?? null,
            ];

            $result = match ($resolved['type']) {
                'fixed' => $fixedCalculator->calculate([$entry])[0] ?? null,
                'flexible' => $flexibleCalculator->calculate([$entry])[0] ?? null,
                'shift' => $shiftCalculator->calculate([$entry])[0] ?? null,
                default => null,
            };

            // working_minutes null berarti checkout ditolak kalkulator (di bawah min_work_minutes,
            // khusus Fixed) - biarkan nilai lama, jangan ditimpa null.
            if (! $result || $result['working_minutes'] === null) {
                $skipped++;

                continue;
            }

            $record->update([
                'working_minutes' => $result['working_minutes'],
                'late_minutes' => $result['late_minutes'] ?? 0,
                'early_leave_minutes' => $result['early_leave_minutes'] ?? 0,
                'status' => $result['status'] ?? $record->status,
            ]);
            $updated++;
        }

        Notification::make()
            ->title('Jam kerja & telat dihitung ulang')
            ->body("{$updated} baris diperbarui sesuai jadwal masing-masing (istirahat dipotong, telat dihitung ulang), {$skipped} baris dilewati (jadwal tidak ditemukan/di bawah minimum jam kerja).")
            ->success()
            ->send();
    }

    // --- Sync ulang per karyawan (hari ini / 1 periode / 1 tanggal di modal rincian) ---

    /** Sync ulang tanggal yang sedang ditampilkan (default hari ini) untuk 1 karyawan (tombol per baris mode harian). */
    public function syncUserToday(string $userId): void
    {
        $user = $this->resolveSyncableUser($userId);
        $today = $this->harianDay();

        $this->notifyResync($user, $today, $today, $this->resyncUserRange($user, $today, $today));
    }

    /** Sync ulang SELURUH periode terpilih untuk 1 karyawan (tombol per baris mode periode). */
    public function syncUserPeriod(string $userId): void
    {
        abort_unless($this->date_from && $this->date_to, 422);

        $user = $this->resolveSyncableUser($userId);

        $this->notifyResync($user, $this->date_from, $this->date_to, $this->resyncUserRange($user, $this->date_from, $this->date_to));
    }

    /** Sync ulang 1 tanggal untuk 1 karyawan (tombol per baris di modal rincian). */
    public function syncUserDate(string $userId, string $date): void
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 422);
        [$from, $to] = $this->monthRange;
        abort_unless($date >= $from && $date <= $to, 422);

        $user = $this->resolveSyncableUser($userId);

        $this->notifyResync($user, $date, $date, $this->resyncUserRange($user, $date, $date));
    }

    /** Karyawan harus termasuk yang sedang terlihat di halaman ini (perusahaan/departemen/divisi) & user boleh sync. */
    private function resolveSyncableUser(string $userId): User
    {
        abort_unless($this->canSync, 403);

        return $this->baseUserQuery()->whereKey($userId)->firstOrFail();
    }

    /** @return array{logs: int, recalculated: int, skipped: int, no_nip: bool} */
    private function resyncUserRange(User $user, string $from, string $to): array
    {
        return app(AttendanceResyncService::class)->resyncUserRange($user, $from, $to);
    }

    private function notifyResync(User $user, string $from, string $to, array $counts): void
    {
        $range = $from === $to
            ? Carbon::parse($from)->translatedFormat('d M Y')
            : Carbon::parse($from)->translatedFormat('d M Y') . ' - ' . Carbon::parse($to)->translatedFormat('d M Y');

        $body = "{$counts['logs']} presensi dibaca ulang dari scan, {$counts['recalculated']} baris jam kerja/telat dihitung ulang, {$counts['skipped']} baris dilewati.";
        if ($counts['no_nip']) {
            $body .= ' NIP karyawan kosong, jadi scan mentah tidak dibaca - hanya hitung ulang baris yang sudah ada.';
        }

        Notification::make()
            ->title("Sync ulang {$user->name} ({$range}) selesai")
            ->body($body)
            ->success()
            ->send();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    /** Klik kartu ringkasan: filter daftar ke status itu; klik lagi (atau kartu Total) = tampilkan semua. */
    public function setStatusFilter(string $key): void
    {
        $allowed = ['present', 'late', 'permit', 'leave', 'sick', 'not_recorded'];

        $this->statusFilter = ($key === $this->statusFilter || ! in_array($key, $allowed, true)) ? '' : $key;
        $this->resetPage();
    }

    public function setScheduleFilter(string $type): void
    {
        $this->scheduleFilter = ($type === $this->scheduleFilter || ! in_array($type, ['fixed', 'flexible', 'shift'], true)) ? '' : $type;
        $this->resetPage();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }


    /**
     * Batasi karyawan ke tipe jadwal tertentu (fixed/flexible/shift) pada rentang $from-$to, mengikuti
     * urutan ScheduleResolver: roster shift (schedule_shift_assignments) menang, lalu assignment
     * personal, lalu assignment jabatan. Karyawan yang punya roster di rentang itu dihitung "shift"
     * (bukan fixed/flexible), walau jabatannya diassign jadwal fixed.
     */
    protected function applyScheduleTypeFilter(Builder $query, ?string $from = null, ?string $to = null): void
    {
        if (! in_array($this->scheduleFilter, ['fixed', 'flexible', 'shift'], true)) {
            return;
        }

        $today = $this->harianDay();
        $from ??= $this->mode === 'harian' ? $today : ($this->date_from ?? $today);
        $to ??= $this->mode === 'harian' ? $today : ($this->date_to ?? $today);
        $type = $this->scheduleFilter;

        $range = 'sa.effective_from <= ? AND (sa.effective_until IS NULL OR sa.effective_until >= ?)';
        $roster = 'EXISTS (SELECT 1 FROM schedule_shift_assignments ssa WHERE ssa.user_id = users.id AND ssa.date BETWEEN ? AND ?)';
        $personalAny = "EXISTS (SELECT 1 FROM schedule_assignments sa WHERE sa.user_id = users.id AND {$range})";
        $personalType = "EXISTS (SELECT 1 FROM schedule_assignments sa JOIN schedules s ON s.id = sa.schedule_id WHERE sa.user_id = users.id AND s.schedule_type = ? AND {$range})";
        $positionType = "EXISTS (SELECT 1 FROM schedule_assignments sa JOIN schedules s ON s.id = sa.schedule_id JOIN user_profiles up ON up.job_position_id = sa.job_position_id WHERE up.user_id = users.id AND s.schedule_type = ? AND {$range})";

        if ($type === 'shift') {
            $query->whereRaw(
                "({$roster} OR {$personalType} OR (NOT {$personalAny} AND {$positionType}))",
                [$from, $to, $type, $to, $from, $to, $from, $type, $to, $from]
            );

            return;
        }

        $query->whereRaw(
            "(NOT {$roster} AND ({$personalType} OR (NOT {$personalAny} AND {$positionType})))",
            [$from, $to, $type, $to, $from, $to, $from, $type, $to, $from]
        );
    }
    protected function baseUserQuery(): Builder
    {
        $query = User::query()
            ->when($this->companyId, fn ($q) => $q->whereCompany($this->companyId));

        $this->applyDepartmentDivisionFilter($query);
        $this->applyScheduleTypeFilter($query);

        return $query;
    }

    protected function filteredUserQuery(): Builder
    {
        $query = $this->baseUserQuery();

        $search = trim($this->search);
        if ($search !== '') {
            $query->searchNameOrNip($search);
        }

        return $query;
    }

    protected function scopedRecordsForRange(string $from, string $to): Builder
    {
        return AttendanceRecords::query()
            ->whereBetween('date', [$from, $to])
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->whereCompany($this->companyId)))
            ->when($this->departmentId || $this->divisionId, fn ($q) => $q->whereHas('user', fn ($q2) => $this->applyDepartmentDivisionFilter($q2)))
            ->when($this->scheduleFilter !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $this->applyScheduleTypeFilter($q2, $from, $to)));
    }

    /** Buang baris yang tanggalnya sudah ditutup Izin Terlambat Masuk (approved) - konsisten
     * dengan KeterlambatanCalculator (payroll) & getMonthlyRowsProperty(), supaya angka "jumlah/
     * menit telat" di ringkasan atas halaman ini juga ikut berkurang (instruksi user 2026-08-10). */
    protected function excludeExcusedLateDates(Builder $query): Builder
    {
        return $query->whereNotExists(function ($q) {
            $q->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('attendance_requests as ar_late')
                ->join('attendance_req_permission_details as pd_late', 'ar_late.id', '=', 'pd_late.request_id')
                ->whereColumn('ar_late.user_id', 'attendance_records.user_id')
                ->whereColumn('pd_late.date', 'attendance_records.date')
                ->where('ar_late.type', 'permission')
                ->where('ar_late.status', AttendanceReqStatus::APPROVED->value)
                ->where('pd_late.category', PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value);
        });
    }

    /** ID user (dari $userIds) yang punya Izin Terlambat Masuk approved di tanggal mode harian -
     * dipakai buat badge biru "Izin" di samping status. Sama kriteria dengan excludeExcusedLateDates(). */
    public function lateExcusedUserIds(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        return AttendanceRequests::query()
            ->whereIn('attendance_requests.user_id', $userIds)
            ->where('attendance_requests.type', 'permission')
            ->where('attendance_requests.status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', fn ($q) => $q
                ->whereDate('date', $this->harianDay())
                ->where('category', PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value))
            ->pluck('user_id')
            ->all();
    }

    protected function queryAppends(): array
    {
        return [
            'mode' => $this->mode,
            'companyId' => $this->companyId,
            'departmentId' => $this->departmentId,
            'divisionId' => $this->divisionId,
            'search' => $this->search,
            'statusFilter' => $this->statusFilter,
            'scheduleFilter' => $this->scheduleFilter,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'tanggal' => $this->harianDate,
            'tanggal_kehadiran' => $this->kehadiranDate,
            'kehadiranFilter' => $this->kehadiranFilter,
        ];
    }

    // --- Mode harian ---

    public function getTodayStatsProperty(): array
    {
        $totalEmployees = $this->baseUserQuery()->count();

        $counts = $this->scopedRecordsForRange($this->harianDay(), $this->harianDay())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recorded = (int) $counts->sum();

        return [
            'total_employees' => $totalEmployees,
            'present' => (int) ($counts[AttendanceStatus::PRESENT->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::LATE->value] ?? 0),
            'permit' => (int) ($counts[AttendanceStatus::PERMIT->value] ?? 0),
            'leave' => (int) ($counts[AttendanceStatus::LEAVE->value] ?? 0),
            'sick' => (int) ($counts[AttendanceStatus::SICK->value] ?? 0),
            'not_recorded' => max(0, $totalEmployees - $recorded),
        ];
    }

    public function getTodayRowsProperty()
    {
        $today = $this->harianDay();

        return $this->filteredUserQuery()
            ->select(['users.id'])
            ->leftJoin('attendance_records', function ($join) use ($today) {
                $join->on('users.id', '=', 'attendance_records.user_id')
                    ->where('attendance_records.date', $today);
            })
            ->addSelect([
                'attendance_records.status as status',
                'attendance_records.check_in_time as check_in_time',
                'attendance_records.check_out_time as check_out_time',
                'attendance_records.late_minutes as late_minutes',
                'attendance_records.working_minutes as working_minutes',
            ])
            ->when($this->statusFilter === 'not_recorded', fn ($q) => $q->whereNull('attendance_records.status'))
            ->when(
                in_array($this->statusFilter, ['present', 'late', 'permit', 'leave', 'sick'], true),
                fn ($q) => $q->where('attendance_records.status', $this->statusFilter)
            )
            ->orderByName()
            ->paginate(15)
            ->appends($this->queryAppends());
    }

    // --- Mode kehadiran (jadwal-aware: siapa SEHARUSNYA masuk tanggal ini vs sudah/belum presensi) ---

    /**
     * Karyawan yang SEHARUSNYA masuk kerja pada $date menurut jadwal masing-masing
     * (ScheduleResolver::resolveForUserDate - roster Shift menang, fallback Fixed/Flexible).
     * Beda dari getTodayStatsProperty() (mode Harian) yang menghitung SEMUA karyawan tanpa
     * peduli hari itu jadwalnya libur atau tidak - di sini yang off_day (atau jadwalnya sama
     * sekali tidak ketemu) dikeluarkan duluan, baru dicocokkan ke attendance_records.
     *
     * Tidak bisa jadi query SQL langsung (jadwal di-resolve per user, bukan kolom di tabel
     * users) - di-loop di PHP, N+1 tapi konsisten dengan pola existing di file ini
     * (recalculateWorkingHours, workingDaysBetween di ScheduleResolver sendiri), bukan endpoint
     * frequent-hit/real-time.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function scheduledUsersForDate(string $date): \Illuminate\Support\Collection
    {
        $resolver = app(ScheduleResolver::class);

        // baseUserQuery() (bukan filteredUserQuery()) - $search TIDAK ikut di sini, sama pola
        // dengan getTodayStatsProperty() (mode Harian): total di kartu ringkasan selalu utuh,
        // pencarian cuma memfilter DAFTAR di getKehadiranRowsProperty(), bukan hitungannya.
        return $this->baseUserQuery()
            ->with('profile')
            ->get()
            ->filter(function (User $user) use ($resolver, $date) {
                $resolved = $resolver->resolveForUserDate($user, $date);

                return $resolved !== null && ! $resolved['is_off_day'];
            })
            ->values();
    }

    /** Baris per karyawan terjadwal + status presensinya tanggal itu (dipakai getKehadiranStatsProperty()
     * & getKehadiranRowsProperty() sekaligus, biar query attendance_records-nya cuma sekali). */
    protected function kehadiranRowsRaw(string $date): \Illuminate\Support\Collection
    {
        $scheduled = $this->scheduledUsersForDate($date);

        $records = AttendanceRecords::query()
            ->whereIn('user_id', $scheduled->pluck('id'))
            ->where('date', $date)
            ->get()
            ->keyBy('user_id');

        $izinLabels = $this->izinLabelsForDate($date, $scheduled->pluck('id'));

        return $scheduled->map(function (User $user) use ($records, $izinLabels) {
            $record = $records->get($user->id);

            $group = match (true) {
                ! $record => 'belum_presensi',
                in_array($record->status, [AttendanceStatus::PRESENT->value, AttendanceStatus::LATE->value], true) => 'hadir',
                in_array($record->status, [AttendanceStatus::PERMIT->value, AttendanceStatus::LEAVE->value, AttendanceStatus::SICK->value], true) => 'izin_cuti_sakit',
                default => 'belum_presensi',
            };

            return (object) [
                'id' => $user->id,
                'name' => $user->name,
                'nip' => $user->nip,
                'status' => $record?->status,
                'check_in_time' => $record?->check_in_time,
                'check_out_time' => $record?->check_out_time,
                'group' => $group,
                'izin_label' => $izinLabels[$user->id] ?? null,
            ];
        })->sortBy('name')->values();
    }

    /**
     * Peta user_id -> label jenis Cuti/Izin (mis. "Cuti Tahunan", "Izin Sakit") dari pengajuan
     * approved yang mencakup tanggal itu - `leave_type`/`permission_type` sudah tersimpan sebagai
     * label jadi (lihat AttendaceReqService::createLeaveRequest()/createPermissionRequest()),
     * tidak perlu resolve enum lagi di sini.
     *
     * @return array<string, string>
     */
    protected function izinLabelsForDate(string $date, \Illuminate\Support\Collection $userIds): array
    {
        $parsedDate = Carbon::parse($date);

        $requests = AttendanceRequests::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('type', ['leave', 'permission'])
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->with(['leaveDetails', 'permissionDetails'])
            ->get();

        $labels = [];

        foreach ($requests as $request) {
            foreach ($request->leaveDetails as $detail) {
                $start = Carbon::parse($detail->start_date);
                $end = Carbon::parse($detail->end_date ?? $detail->start_date);

                if ($parsedDate->between($start, $end)) {
                    $labels[$request->user_id] = $detail->leave_type;
                }
            }

            foreach ($request->permissionDetails as $detail) {
                $start = Carbon::parse($detail->date);
                $end = Carbon::parse($detail->end_date ?? $detail->date);

                if ($parsedDate->between($start, $end)) {
                    $labels[$request->user_id] = $detail->permission_type;
                }
            }
        }

        return $labels;
    }

    public function getKehadiranStatsProperty(): array
    {
        $rows = $this->kehadiranRowsRaw($this->kehadiranDay());
        $grouped = $rows->countBy('group');

        return [
            'total_scheduled' => $rows->count(),
            'hadir' => (int) ($grouped['hadir'] ?? 0),
            'izin_cuti_sakit' => (int) ($grouped['izin_cuti_sakit'] ?? 0),
            'belum_presensi' => (int) ($grouped['belum_presensi'] ?? 0),
        ];
    }

    /** Dipaginate manual (bukan ->paginate() Eloquent) karena sumbernya Collection hasil filter
     * jadwal di PHP, bukan Query Builder - lihat scheduledUsersForDate(). */
    public function getKehadiranRowsProperty(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $rows = $this->kehadiranRowsRaw($this->kehadiranDay());

        $search = trim($this->search);
        if ($search !== '') {
            $needle = strtolower($search);
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row->name), $needle) || str_contains(strtolower((string) $row->nip), $needle))->values();
        }

        if ($this->kehadiranFilter !== '') {
            $rows = $rows->filter(fn ($row) => $row->group === $this->kehadiranFilter)->values();
        }

        $perPage = 15;
        $page = $this->getPage();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $this->queryAppends()],
        );
    }

    // --- Mode bulanan (sebenarnya per-periode, ikut cutoff company - lihat mount()) ---

    public function getMonthRangeProperty(): array
    {
        return [$this->date_from, $this->date_to];
    }

    public function getMonthlyStatsProperty(): array
    {
        [$from, $to] = $this->monthRange;

        return [
            'total_employees' => $this->baseUserQuery()->count(),
            'total_present_days' => $this->scopedRecordsForRange($from, $to)->count(),
            'total_late' => $this->excludeExcusedLateDates(
                $this->scopedRecordsForRange($from, $to)->where('status', AttendanceStatus::LATE->value)
            )->count(),
            'total_late_minutes' => (int) $this->excludeExcusedLateDates($this->scopedRecordsForRange($from, $to))->sum('late_minutes'),
            'total_working_hours' => round($this->scopedRecordsForRange($from, $to)->sum('working_minutes') / 60, 1),
        ];
    }

    public function getMonthlyRowsProperty()
    {
        [$from, $to] = $this->monthRange;
        $totalDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        return $this->filteredUserQuery()
            ->select(['users.id'])
            ->selectRaw('? as total_days_in_period', [$totalDays])
            ->selectRaw('COUNT(DISTINCT attendance_records.date) as present_count')
            ->selectRaw('(? - COUNT(DISTINCT attendance_records.date)) as absent_count', [$totalDays])
            // Tanggal yang sudah ditutup Izin Terlambat Masuk (approved) tidak ikut dihitung
            // sebagai keterlambatan di sini - konsisten dengan KeterlambatanCalculator (payroll),
            // supaya "jumlah/menit telat" yang ditampilkan di rekap ini juga berkurang, bukan
            // cuma potongan gajinya (instruksi user 2026-08-10).
            ->selectRaw('COUNT(CASE WHEN attendance_records.status = "late" AND NOT EXISTS (
                SELECT 1 FROM attendance_requests ar_late
                INNER JOIN attendance_req_permission_details pd_late ON ar_late.id = pd_late.request_id
                WHERE ar_late.user_id = users.id AND ar_late.type = "permission" AND ar_late.status = "approved"
                AND pd_late.category = "izin_terlambat_masuk" AND pd_late.date = attendance_records.date
            ) THEN 1 END) as late_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN NOT EXISTS (
                SELECT 1 FROM attendance_requests ar_late2
                INNER JOIN attendance_req_permission_details pd_late2 ON ar_late2.id = pd_late2.request_id
                WHERE ar_late2.user_id = users.id AND ar_late2.type = "permission" AND ar_late2.status = "approved"
                AND pd_late2.category = "izin_terlambat_masuk" AND pd_late2.date = attendance_records.date
            ) THEN attendance_records.late_minutes ELSE 0 END), 0) as total_late_minutes')
            ->selectRaw('COALESCE(SUM(attendance_records.working_minutes), 0) as total_working_minutes')
            ->selectRaw('ROUND(AVG(attendance_records.working_minutes) / 60, 1) as avg_working_hours')
            ->selectRaw('COALESCE((
                SELECT ROUND(SUM(
                    TIMESTAMPDIFF(MINUTE, CONCAT(pd.date, " ", pd.start_time), CONCAT(pd.date, " ", pd.end_time)) / 60.0
                ), 1)
                FROM attendance_requests ar
                INNER JOIN attendance_req_permission_details pd ON ar.id = pd.request_id
                WHERE ar.user_id = users.id AND ar.type = "permission" AND ar.status = "approved"
                AND pd.date BETWEEN ? AND ?
            ), 0) as permission_hours', [$from, $to])
            ->selectRaw('COALESCE((
                SELECT SUM(DATEDIFF(LEAST(ld.end_date, ?), GREATEST(ld.start_date, ?)) + 1)
                FROM attendance_requests ar
                INNER JOIN attendance_req_leave_details ld ON ar.id = ld.request_id
                WHERE ar.user_id = users.id AND ar.type = "leave" AND ar.status = "approved"
                AND ld.start_date <= ? AND ld.end_date >= ?
            ), 0) as leave_days', [$to, $from, $to, $from])
            ->selectRaw('COALESCE((
                SELECT ROUND(SUM(od.total_hours), 1)
                FROM attendance_requests ar
                INNER JOIN attendance_req_overtime_details od ON ar.id = od.request_id
                WHERE ar.user_id = users.id AND ar.type = "overtime" AND ar.status = "approved"
                AND od.date BETWEEN ? AND ?
            ), 0) as overtime_hours', [$from, $to])
            ->leftJoin('attendance_records', function ($join) use ($from, $to) {
                $join->on('users.id', '=', 'attendance_records.user_id')
                    ->whereBetween('attendance_records.date', [$from, $to]);
            })
            ->groupBy('users.id')
            ->orderByName()
            ->paginate(15)
            ->appends($this->queryAppends());
    }

    // --- Modal detail harian (klik baris di mode bulanan) ---

    public function openDetail(string $userId): void
    {
        $this->selectedUserId = $userId;
    }

    public function closeDetail(): void
    {
        $this->selectedUserId = null;
    }

    public function getSelectedUserProperty(): ?User
    {
        if (! $this->selectedUserId) {
            return null;
        }

        return $this->baseUserQuery()->find($this->selectedUserId);
    }

    /**
     * SATU baris per TANGGAL KALENDER dalam periode (date_from/date_to), bukan cuma tanggal yang
     * kebetulan punya baris attendance_records - approve Cuti/Izin TIDAK otomatis bikin baris
     * attendance_records (tidak ada listener/job yang nulis ke sana saat approval, cuma pipeline
     * scan/sync/import yang nulis), jadi kalau modal cuma nampilin baris yang ADA di
     * attendance_records, tanggal Cuti multi-hari yang karyawannya sama sekali tidak
     * scan sepanjang cuti itu (kasus paling umum) malah HILANG dari tabel - cross-check Cuti/Izin
     * di kolom paling kanan jadi kelihatan "tidak jalan" padahal barisnya memang tidak pernah
     * muncul. Tanggal tanpa baris attendance_records ditandai `has_record = false`, status null -
     * blade yang mutuskan tampilannya (dicocokkan ke selectedUserLeaveOrPermissionDates juga).
     */
    public function getSelectedUserDetailRowsProperty()
    {
        if (! $this->selectedUserId) {
            return collect();
        }

        [$from, $to] = $this->monthRange;

        $records = AttendanceRecords::query()
            ->where('user_id', $this->selectedUserId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy('date');

        $days = collect();

        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $dateStr = $d->toDateString();
            $record = $records->get($dateStr);

            $days->push((object) [
                'date' => $dateStr,
                'has_record' => (bool) $record,
                'status' => $record?->status,
                'check_in_time' => $record?->check_in_time,
                'check_out_time' => $record?->check_out_time,
                'late_minutes' => $record?->late_minutes,
                'working_minutes' => $record?->working_minutes,
            ]);
        }

        return $days;
    }

    /**
     * Peta tanggal -> pengajuan Cuti/Izin karyawan terpilih (approved/masih diproses -
     * submitted/in_review ikut, ditolak/dibatalkan/gagal TIDAK) dalam periode - dipakai modal
     * detail buat baris berstatus 'absent': ada kemungkinan sebenarnya lagi Cuti/Izin (belum
     * sempat diproses sync/approval) vs benar-benar mangkir tanpa pengajuan sama sekali.
     *
     * @return array<string, array{type: string, label: string, status: AttendanceReqStatus}>
     */
    public function getSelectedUserLeaveOrPermissionDatesProperty(): array
    {
        if (! $this->selectedUserId) {
            return [];
        }

        [$from, $to] = $this->monthRange;
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);

        $consideredStatuses = [
            AttendanceReqStatus::APPROVED->value,
            AttendanceReqStatus::SUBMITTED->value,
            AttendanceReqStatus::IN_REVIEW->value,
        ];

        $requests = AttendanceRequests::query()
            ->where('user_id', $this->selectedUserId)
            ->whereIn('type', ['leave', 'permission'])
            ->whereIn('status', $consideredStatuses)
            ->with(['leaveDetails', 'permissionDetails'])
            ->get();

        $map = [];

        foreach ($requests as $request) {
            $status = AttendanceReqStatus::from($request->status);
            $label = $request->type === 'leave' ? 'Cuti' : 'Izin';

            foreach ($request->leaveDetails as $detail) {
                $start = Carbon::parse($detail->start_date)->max($fromDate);
                $end = Carbon::parse($detail->end_date ?? $detail->start_date)->min($toDate);

                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $map[$d->toDateString()] = ['type' => 'leave', 'label' => $label, 'status' => $status];
                }
            }

            foreach ($request->permissionDetails as $detail) {
                $start = Carbon::parse($detail->date)->max($fromDate);
                $end = Carbon::parse($detail->end_date ?? $detail->date)->min($toDate);

                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $map[$d->toDateString()] = ['type' => 'permission', 'label' => $label, 'status' => $status];
                }
            }
        }

        return $map;
    }

    public function render()
    {
        return view('livewire.presensi.rekap-presensi-page');
    }
}
