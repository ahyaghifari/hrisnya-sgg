<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Concerns\FiltersByDepartmentDivision;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\OvertimeAttendanceRecord;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Services\Attendance\AttendancePeriodResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rekap seluruh pengajuan Lembur (APPROVED) dalam 1 periode — matrix karyawan x jenis lembur.
 * Sama seperti CutiRecapPage: kolom jenis dinamis dari nama overtime_type yang muncul di data
 * (jenis lembur cuma 2, lihat OvertimeTypeEnum - tanpa kategori seperti Izin). Angka yang
 * dihitung = total JAM lembur (total_hours), bukan jumlah pengajuan - lebih relevan untuk HC
 * (dasar hitung kompensasi lembur), 1 pengajuan bisa punya beberapa baris detail/tanggal.
 */
#[Layout('components.layout', ['title' => 'Rekap Lembur'])]
class LemburRecapPage extends Component
{
    use GatesCompanyFilterBySystem, FiltersByDepartmentDivision;

    public ?string $companyId = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $selectedUserId = null;
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value), 403);

        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_ABSENSI);

        if ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }

        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve(now(), $this->companyId);
        $this->date_from = $periodStart->toDateString();
        $this->date_to = $periodEnd->toDateString();
    }

    public function updatedCompanyId(): void
    {
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_ABSENSI);

        $this->departmentId = null;
        $this->divisionId = null;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_ABSENSI);
    }

    public function openDetail(string $userId): void
    {
        $this->selectedUserId = $userId;
    }

    public function closeDetail(): void
    {
        $this->selectedUserId = null;
    }

    public function export()
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Presensi\LemburRecapExport($this->recap['rows']),
            "rekap-lembur_{$this->date_from}_{$this->date_to}.xlsx",
        );
    }

    public function getCanRecheckProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::SYNC_DATA_ABSENSI->value);
    }

    /** Baca ulang scan access_logs tiap tanggal lembur (1 karyawan, atau semua di rekap kalau
     *  $userId null) - scan yang masuk sebelum pengajuan disetujui jadi terkoreksi. */
    public function recheckPresensi(?string $userId = null): void
    {
        abort_unless($this->canRecheck, 403);

        $resweep = app(\App\Services\Attendance\AccessLogResweepService::class);
        $count = 0;

        foreach ($this->recap['rows'] as $row) {
            if (($userId && $row['user']->id !== $userId) || ! filled($row['user']->nip)) {
                continue;
            }

            foreach (collect($row['items'])->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->unique() as $date) {
                $resweep->resweepUserDate($row['user']->nip, $date);
                $count++;
            }
        }

        \Filament\Notifications\Notification::make()->title("Presensi diperiksa ulang ({$count} tanggal)")->success()->send();
    }

    /**
     * @return array{
     *     types: array<string>,
     *     rows: array<int, array{user: \App\Models\User, counts: array<string, float>, total: float, items: array}>,
     *     overall: array{counts: array<string, float>, total: float, employee_count: int, request_count: int},
     *     ready: bool
     * }
     */
    public function getRecapProperty(): array
    {
        if (! $this->date_from || ! $this->date_to) {
            return ['types' => [], 'rows' => [], 'overall' => ['counts' => [], 'total' => 0, 'employee_count' => 0, 'request_count' => 0], 'ready' => false];
        }

        $details = AttendanceRequestOvertimeDetails::query()
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('type', 'overtime')
                ->where('status', 'approved')
                ->whereHas('user', function ($q2) {
                    $q2->when($this->companyId, fn ($q3) => $q3->whereCompany($this->companyId));
                    $this->applyDepartmentDivisionFilter($q2);
                }))
            ->with(['attendanceRequest.user', 'attendanceRequest.attachments', 'attendanceRequest.user.profile.jobPosition.orgStructure.parent.parent'])
            ->get()
            ->filter(fn ($d) => $d->attendanceRequest?->user);

        $types = $details->pluck('overtime_type')->filter()->unique()->sort()->values()->all();
        $emptyCounts = collect($types)->mapWithKeys(fn ($t) => [$t => 0.0])->all();

        // Bukti presensi per (user, tanggal): scan lembur hari libur (overtime_attendance_records)
        // diutamakan, fallback presensi biasa (attendance_records) - null = belum presensi.
        $userIds = $details->map(fn ($d) => $d->attendanceRequest->user_id)->unique()->all();
        $range = [$this->date_from, $this->date_to];
        $presensi = AttendanceRecords::query()->whereIn('user_id', $userIds)->whereBetween('date', $range)->whereNotNull('check_in_time')->get()
            ->mapWithKeys(fn ($r) => [$r->user_id . '|' . substr((string) $r->date, 0, 10) => [$r->check_in_time, $r->check_out_time]]);
        $presensi = $presensi->merge(
            OvertimeAttendanceRecord::query()->whereIn('user_id', $userIds)->whereBetween('date', $range)->get()
                ->mapWithKeys(fn ($r) => [$r->user_id . '|' . substr((string) $r->date, 0, 10) => [$r->check_in_time, $r->check_out_time]])
        );

        $rows = $details->groupBy(fn ($d) => $d->attendanceRequest->user_id)->map(function ($group) use ($emptyCounts, $presensi) {
            $user   = $group->first()->attendanceRequest->user;
            $counts = $emptyCounts;

            $items = $group->map(function ($detail) use (&$counts, $presensi) {
                $type = $detail->overtime_type ?: 'Lainnya';
                $counts[$type] = ($counts[$type] ?? 0) + (float) $detail->total_hours;

                return [
                    'request_id' => $detail->request_id,
                    // Pratinjau lampiran (baca-saja) di modal rincian: url + jenis, tanpa aksi apa pun.
                    'attachments' => $detail->attendanceRequest->attachments->map(fn ($a) => [
                        'url' => $a->file_url,
                        'is_image' => $a->is_image,
                        'is_link' => $a->is_link,
                        'label' => $a->is_link
                            ? (preg_replace('/^www\./', '', parse_url($a->file_path, PHP_URL_HOST) ?: 'Link'))
                            : strtoupper(pathinfo($a->file_path, PATHINFO_EXTENSION) ?: 'FILE'),
                    ])->all(),
                    'presensi'   => $presensi->get($detail->attendanceRequest->user_id . '|' . substr((string) $detail->date, 0, 10)),
                    'type_name'  => $type,
                    'date'       => $detail->date,
                    'start_time' => $detail->start_time,
                    'end_time'   => $detail->end_time,
                    'total_hours' => (float) $detail->total_hours,
                    'task'       => $detail->task_description,
                ];
            })->sortBy('date')->values()->all();

            return [
                'user'   => $user,
                'counts' => $counts,
                'total'  => array_sum($counts),
                'items'  => $items,
            ];
        })
            ->sortByDesc('total')
            ->values();

        $overallCounts = $emptyCounts;
        foreach ($rows as $row) {
            foreach ($row['counts'] as $key => $value) {
                $overallCounts[$key] += $value;
            }
        }
        $employeeCount = $rows->count();

        $search = trim($this->search);
        if ($search !== '') {
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower($row['user']->name), mb_strtolower($search))
                || str_contains(mb_strtolower($row['user']->nip ?? ''), mb_strtolower($search)));
        }

        $maxTotal = $rows->max('total') ?: 1;
        $rows = $rows->map(fn ($row) => $row + ['bar_percent' => (int) round($row['total'] / $maxTotal * 100)])
            ->values()
            ->all();

        return [
            'types' => $types,
            'rows' => $rows,
            'overall' => [
                'counts' => $overallCounts,
                'total' => array_sum($overallCounts),
                'employee_count' => $employeeCount,
                'request_count' => $details->count(),
            ],
            'ready' => true,
        ];
    }

    public function render()
    {
        return view('livewire.presensi.lembur-recap-page');
    }
}
