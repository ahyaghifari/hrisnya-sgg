<?php

namespace App\Livewire;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Exports\Attendance\AttendanceHistoryExport;
use App\Livewire\Concerns\HandlesProfilePhotoUpload;
use App\Livewire\Concerns\ResolvesTodayAttendanceStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\User;
use App\Models\User\UserDocuments;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeAttendanceStatistics extends Component
{
    use ResolvesTodayAttendanceStatus;
    use HandlesProfilePhotoUpload;

    public User $record;

    public ?string $date_from = null;
    public ?string $date_to = null;
    public string $status = 'approved';

    public string $calendarMonth;

    public function mount(?User $record = null): void
    {
        // Route /data-diri tidak punya segment {record} — otomatis pakai user yang login.
        $record ??= Auth::user();

        abort_unless($this->canManage($record), 403);

        $this->record = $record->load([
            'company',
            'roles',
            'profile.religion',
            'profile.bank',
            'profile.orgStructure.parent.parent',
            'profile.jobPosition',
            'profile.pangkat',
            'profile.golongan',
            'profile.kelas',
            'profile.employeeType',
        ]);
        // Default periode ikut cutoff absensi company (AttendancePeriodResolver, sama dengan
        // yang dipakai enforcement kuota Cuti/Izin) — bukan kalender 1-31 tetap. User tetap
        // bisa timpa manual lewat filter Dari/Sampai Tanggal.
        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve(now(), $this->record->company_id);
        $this->date_from = $periodStart->toDateString();
        $this->date_to = $periodEnd->toDateString();

        $this->calendarMonth = now()->format('Y-m');
    }

    protected function canManage(User $record): bool
    {
        $user = Auth::user();

        // Karyawan boleh lihat data dirinya sendiri lewat halaman ini juga (tombol "Data Diri"
        // di Dashboard) — bukan cuma atasan yang lihat bawahannya.
        if ($record->id === $user->id) {
            return true;
        }

        if ($user->canBrowseAllCompanies()) {
            return true;
        }

        // HC/Kepegawaian company-wide (bukan cuma atasan lihat bawahan) — dibatasi ke
        // company sendiri, konsisten dengan scoping EmployeeResource di admin.
        if ($user->can(\App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value) && $user->company_id === $record->company_id) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'status' => $this->status !== 'all' ? $this->status : null,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ];
    }

    public function getRequestsProperty()
    {
        return app(AttendaceReqService::class)
            ->getListAttendanceRequestsByUserId($this->record->id, $this->filters());
    }

    /**
     * @return array{leave: int, permission: int, overtime: int}
     */
    public function getStatsProperty(): array
    {
        $requests = $this->requests;

        return [
            'leave' => $requests->where('type', 'leave')->count(),
            'permission' => $requests->where('type', 'permission')->count(),
            'overtime' => $requests->where('type', 'overtime')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getPresenceStatsProperty(): array
    {
        $counts = AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $case) => [$case->value => $counts[$case->value] ?? 0])
            ->toArray();
    }

    public function getTodayPresenceProperty(): ?AttendanceRecords
    {
        return AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->where('date', now()->toDateString())
            ->first();
    }

    public function getTodayAttendanceStatusProperty(): array
    {
        return $this->todayAttendanceStatus($this->record, $this->todayPresence);
    }

    public function getPresenceRecordsProperty()
    {
        return AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->orderByDesc('date')
            ->get();
    }

    public function getCalendarMonthLabelProperty(): string
    {
        return Carbon::parse($this->calendarMonth . '-01')->locale('id')->translatedFormat('F Y');
    }

    public function prevMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->addMonthNoOverflow()->format('Y-m');
    }

    /**
     * Grid kalender bulan berjalan, Senin-Minggu, sama pola dengan HolidaysPage - sel null buat
     * padding sebelum tanggal 1 & setelah tanggal terakhir. Tiap sel bawa presensi hari itu
     * (attendance_records) DAN pengajuan yang menyentuh hari itu (approved/submitted/in_review -
     * bukan cuma approved, biar kelihatan juga yang masih proses persetujuan).
     *
     * @return array<int, array{date: Carbon, attendance: ?AttendanceRecords, requests: \Illuminate\Support\Collection}|null>
     */
    public function getCalendarDaysProperty(): array
    {
        $start = Carbon::parse($this->calendarMonth . '-01');
        $end   = $start->copy()->endOfMonth();

        $attendanceByDate = AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRecords $r) => Carbon::parse($r->date)->toDateString());

        $requestsByDate = $this->requestMarkersForMonth($start, $end);

        $leadingBlanks = $start->dayOfWeekIso - 1; // Senin=1 -> 0 blank, Minggu=7 -> 6 blank
        $cells = array_fill(0, $leadingBlanks, null);

        for ($day = 1; $day <= $end->day; $day++) {
            $date = $start->copy()->day($day);
            $dateKey = $date->toDateString();
            $cells[] = [
                'date'       => $date,
                'attendance' => $attendanceByDate->get($dateKey),
                'requests'   => $requestsByDate->get($dateKey, collect()),
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return $cells;
    }

    /**
     * Kumpulkan marker pengajuan (cuti/izin/lembur, approved+submitted+in_review) yang
     * menyentuh bulan ini, dikelompokkan per tanggal. Cuti dipakai rentang start_date/end_date
     * (null end_date = 1 hari), izin/lembur dipakai tanggal tunggal per detail baris.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection>
     */
    private function requestMarkersForMonth(Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        $activeStatuses = ['approved', 'submitted', 'in_review'];
        $markers = collect();

        $addMarker = function (string $dateKey, string $type, string $status) use (&$markers) {
            $existing = $markers->get($dateKey, collect());
            $markers->put($dateKey, $existing->push(['type' => $type, 'status' => $status]));
        };

        $requests = AttendanceRequests::query()
            ->where('user_id', $this->record->id)
            ->whereIn('status', $activeStatuses)
            ->whereIn('type', ['leave', 'permission', 'overtime'])
            ->with(['leaveDetails', 'permissionDetails', 'overtimeDetails'])
            ->get();

        foreach ($requests as $request) {
            foreach ($request->leaveDetails as $detail) {
                $rangeStart = Carbon::parse($detail->start_date);
                $rangeEnd   = $detail->end_date ? Carbon::parse($detail->end_date) : $rangeStart->copy();

                $clampStart = $rangeStart->lt($start) ? $start->copy() : $rangeStart;
                $clampEnd   = $rangeEnd->gt($end) ? $end->copy() : $rangeEnd;

                for ($d = $clampStart->copy(); $d->lte($clampEnd); $d->addDay()) {
                    $addMarker($d->toDateString(), 'leave', $request->status);
                }
            }

            foreach ($request->permissionDetails as $detail) {
                $date = Carbon::parse($detail->date);
                if ($date->between($start, $end)) {
                    $addMarker($date->toDateString(), 'permission', $request->status);
                }
            }

            foreach ($request->overtimeDetails as $detail) {
                $date = Carbon::parse($detail->date);
                if ($date->between($start, $end)) {
                    $addMarker($date->toDateString(), 'overtime', $request->status);
                }
            }
        }

        return $markers;
    }

    public function getDocumentsProperty()
    {
        return UserDocuments::query()
            ->where('user_id', $this->record->id)
            ->orderByDesc('issued_date')
            ->get();
    }

    private function keterangan(AttendanceRequests $record): string
    {
        return match ($record->type) {
            'leave' => $record->leaveDetails->first()?->leave_type ?? 'Cuti',
            'permission' => $record->permissionDetails->first()?->permission_type ?? 'Izin',
            'overtime' => $record->overtimeDetails->first()?->overtime_type ?? 'Lembur',
            default => '-',
        };
    }

    public function export()
    {
        $rows = $this->requests->map(fn (AttendanceRequests $r) => [
            'date' => $r->created_at->translatedFormat('d M Y H:i'),
            'type' => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$r->type] ?? $r->type,
            'keterangan' => $this->keterangan($r),
            'status' => AttendanceReqStatus::from($r->status)->getLabel(),
        ])->toArray();

        $periodLabel = "{$this->date_from} s/d {$this->date_to}";

        return Excel::download(
            new AttendanceHistoryExport($rows, $this->record->name, $periodLabel),
            'riwayat-absensi-' . str($this->record->name)->slug() . '.xlsx'
        );
    }

    public function render()
    {
        $title = $this->record->id === Auth::id()
            ? 'Data Diri'
            : "MyStaff - {$this->record->name}";

        return view('livewire.employee-attendance-statistics')
            ->layout('components.layout', ['title' => $title]);
    }
}
