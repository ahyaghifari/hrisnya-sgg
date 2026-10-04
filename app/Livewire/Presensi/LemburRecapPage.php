<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Concerns\FiltersByDepartmentDivision;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
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
            ->with(['attendanceRequest.user', 'attendanceRequest.user.profile.jobPosition.orgStructure.parent.parent'])
            ->get()
            ->filter(fn ($d) => $d->attendanceRequest?->user);

        $types = $details->pluck('overtime_type')->filter()->unique()->sort()->values()->all();
        $emptyCounts = collect($types)->mapWithKeys(fn ($t) => [$t => 0.0])->all();

        $rows = $details->groupBy(fn ($d) => $d->attendanceRequest->user_id)->map(function ($group) use ($emptyCounts) {
            $user   = $group->first()->attendanceRequest->user;
            $counts = $emptyCounts;

            $items = $group->map(function ($detail) use (&$counts) {
                $type = $detail->overtime_type ?: 'Lainnya';
                $counts[$type] = ($counts[$type] ?? 0) + (float) $detail->total_hours;

                return [
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
