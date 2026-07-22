<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rekap seluruh pengajuan Lembur (APPROVED) dalam 1 periode — matrix karyawan x jenis lembur.
 * Sama seperti CutiRecapPage: kolom jenis dinamis dari nama overtime_type yang muncul di data
 * (m_overtime_types tidak punya kategori tetap sama sekali, beda dari Izin). Angka yang
 * dihitung = total JAM lembur (total_hours), bukan jumlah pengajuan - lebih relevan untuk HC
 * (dasar hitung kompensasi lembur), 1 pengajuan bisa punya beberapa baris detail/tanggal.
 */
#[Layout('components.layout', ['title' => 'Rekap Lembur'])]
class LemburRecapPage extends Component
{
    public ?string $companyId = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $selectedUserId = null;
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);

        if (! PermissionHelper::hasGlobalRole(Auth::user())) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return PermissionHelper::hasGlobalRole(Auth::user());
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
                ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId))))
            ->with('attendanceRequest.user')
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
        return view('livewire.kepegawaian.lembur-recap-page');
    }
}
