<?php

namespace App\Livewire\Presensi;

use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\User;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
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
 * karyawan 1 bulan, mirror App\Filament\...\AttendanceReport::applyAttendanceMetrics() tapi query
 * di-parameterize pakai selectRaw(..., $bindings) - bukan string-interpolate raw SQL seperti versi
 * admin - dan kolom dipangkas ke yang penting saja (tanpa early_leave/overtime aktual toggleable).
 */
#[Layout('components.layout', ['title' => 'Rekap Presensi'])]
class RekapPresensiPage extends Component
{
    use WithPagination;

    #[Url]
    public string $mode = 'harian';

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $month = '';

    public bool $showSyncModal = false;

    public string $syncDate = '';

    public string $syncFromTime = '08:00';

    public ?string $syncResultMessage = null;

    public ?string $syncResultType = null;

    public array $syncNewRecords = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        // Admin (bukan Super Admin) TETAP dikunci ke company sendiri, apapun isi companyId
        // dari query string (proteksi, bukan cuma default). Super Admin boleh lihat semua
        // perusahaan, tapi default awal (belum ada companyId di query string) diarahkan ke
        // company dia sendiri dulu - bukan langsung nampilin semua company sekaligus.
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }

        if ($this->month === '') {
            $this->month = now()->format('Y-m');
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCanSyncProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::SYNC_DATA_ABSENSI->value);
    }

    public function openSyncModal(): void
    {
        abort_unless($this->canSync, 403);

        $this->syncDate = now()->toDateString();
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

        $this->syncNewRecords = $newRecords;
        $this->syncResultType = 'success';
        $this->syncResultMessage = "Sync selesai: {$fixedCount} jadwal tetap, {$flexibleCount} jadwal fleksibel diproses, "
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

    public function updatedMonth(): void
    {
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

    protected function baseUserQuery(): Builder
    {
        return User::query()->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId));
    }

    protected function filteredUserQuery(): Builder
    {
        $query = $this->baseUserQuery();

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('users.nip', 'like', "%{$search}%"));
        }

        return $query;
    }

    protected function scopedRecordsForRange(string $from, string $to): Builder
    {
        return AttendanceRecords::query()
            ->whereBetween('date', [$from, $to])
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)));
    }

    protected function queryAppends(): array
    {
        return [
            'mode' => $this->mode,
            'companyId' => $this->companyId,
            'search' => $this->search,
            'month' => $this->month,
        ];
    }

    // --- Mode harian ---

    public function getTodayStatsProperty(): array
    {
        $totalEmployees = $this->baseUserQuery()->count();

        $counts = $this->scopedRecordsForRange(now()->toDateString(), now()->toDateString())
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
        $today = now()->toDateString();

        return $this->filteredUserQuery()
            ->select(['users.id', 'users.name', 'users.nip'])
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
            ->orderBy('users.name')
            ->paginate(15)
            ->appends($this->queryAppends());
    }

    // --- Mode bulanan ---

    public function getMonthRangeProperty(): array
    {
        $start = Carbon::parse($this->month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    public function getMonthlyStatsProperty(): array
    {
        [$from, $to] = $this->monthRange;

        return [
            'total_employees' => $this->baseUserQuery()->count(),
            'total_present_days' => $this->scopedRecordsForRange($from, $to)->count(),
            'total_late' => $this->scopedRecordsForRange($from, $to)->where('status', AttendanceStatus::LATE->value)->count(),
            'total_late_minutes' => (int) $this->scopedRecordsForRange($from, $to)->sum('late_minutes'),
            'total_working_hours' => round($this->scopedRecordsForRange($from, $to)->sum('working_minutes') / 60, 1),
        ];
    }

    public function getMonthlyRowsProperty()
    {
        [$from, $to] = $this->monthRange;
        $totalDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        return $this->filteredUserQuery()
            ->select(['users.id', 'users.name', 'users.nip'])
            ->selectRaw('? as total_days_in_period', [$totalDays])
            ->selectRaw('COUNT(DISTINCT attendance_records.date) as present_count')
            ->selectRaw('(? - COUNT(DISTINCT attendance_records.date)) as absent_count', [$totalDays])
            ->selectRaw('COUNT(CASE WHEN attendance_records.status = "late" THEN 1 END) as late_count')
            ->selectRaw('COALESCE(SUM(attendance_records.late_minutes), 0) as total_late_minutes')
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
            ->groupBy('users.id', 'users.name', 'users.nip')
            ->orderBy('users.name')
            ->paginate(15)
            ->appends($this->queryAppends());
    }

    public function render()
    {
        return view('livewire.presensi.rekap-presensi-page');
    }
}
