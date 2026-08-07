<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Services\Attendance\AttendancePeriodResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rekap seluruh pengajuan Cuti (APPROVED) — 2 mode: "matrix" (ringkasan karyawan x jenis cuti,
 * 1 periode) dan "table" (histori flat semua pengajuan cuti, terbaru dulu, tanpa terikat 1
 * periode - mirip riwauat-cuti-sebelum-update-hris.png). Beda dari IzinRecapPage: jenis cuti
 * TIDAK dipetakan ke enum tetap (LeaveTypeCategoryEnum cuma punya 1 case, "tahunan" - tidak
 * representatif buat semua jenis cuti), jadi kolom kategori di mode matrix dinamis dari nama
 * jenis cuti (leave_type) yang benar-benar muncul di data periode itu. Angka yang dihitung di
 * mode matrix = HARI cuti yang jatuh dalam periode filter (diclamp ke rentang filter, bukan
 * total_days penuh kalau requestnya sebagian di luar periode).
 */
#[Layout('components.layout', ['title' => 'Rekap Cuti'])]
class CutiRecapPage extends Component
{
    use WithPagination;

    #[Url]
    public string $mode = 'matrix';

    public ?string $companyId = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $selectedUserId = null;
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }

        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve(now(), $this->companyId);
        $this->date_from = $periodStart->toDateString();
        $this->date_to = $periodEnd->toDateString();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->resetPage();
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

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
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
     *     rows: array<int, array{user: \App\Models\User, counts: array<string, int>, total: int, items: array}>,
     *     overall: array{counts: array<string, int>, total: int, employee_count: int, request_count: int},
     *     ready: bool
     * }
     */
    public function getRecapProperty(): array
    {
        if (! $this->date_from || ! $this->date_to) {
            return ['types' => [], 'rows' => [], 'overall' => ['counts' => [], 'total' => 0, 'employee_count' => 0, 'request_count' => 0], 'ready' => false];
        }

        $rangeStart = Carbon::parse($this->date_from);
        $rangeEnd   = Carbon::parse($this->date_to);

        $requests = AttendanceRequests::query()
            ->where('type', 'leave')
            ->where('status', 'approved')
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->whereHas('leaveDetails', fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('end_date')->where('start_date', '<=', $this->date_to)->where('end_date', '>=', $this->date_from))
                ->orWhere(fn ($q2) => $q2->whereNull('end_date')->whereBetween('start_date', [$this->date_from, $this->date_to]))
            )
            ->with(['user', 'leaveDetails'])
            ->get()
            ->filter(fn ($r) => $r->user);

        // Kolom dinamis - kumpulkan semua nama jenis cuti yang benar-benar muncul, urut abjad.
        $types = $requests->flatMap(fn ($r) => $r->leaveDetails->pluck('leave_type'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $emptyCounts = collect($types)->mapWithKeys(fn ($t) => [$t => 0])->all();

        $rows = $requests->groupBy('user_id')->map(function ($group) use ($emptyCounts, $rangeStart, $rangeEnd) {
            $user   = $group->first()->user;
            $counts = $emptyCounts;
            $items  = [];

            foreach ($group as $request) {
                foreach ($request->leaveDetails as $detail) {
                    $detailStart = Carbon::parse($detail->start_date);
                    $detailEnd   = $detail->end_date ? Carbon::parse($detail->end_date) : $detailStart->copy();

                    $clampStart = $detailStart->lt($rangeStart) ? $rangeStart->copy() : $detailStart;
                    $clampEnd   = $detailEnd->gt($rangeEnd) ? $rangeEnd->copy() : $detailEnd;

                    if ($clampStart->gt($clampEnd)) {
                        continue;
                    }

                    $days = $this->countDaysExcludingSunday($clampStart, $clampEnd);
                    $type = $detail->leave_type ?: 'Lainnya';

                    $counts[$type] = ($counts[$type] ?? 0) + $days;

                    $items[] = [
                        'type_name'  => $type,
                        'start_date' => $detail->start_date,
                        'end_date'   => $detail->end_date,
                        'total_days' => $days,
                    ];
                }
            }

            return [
                'user'   => $user,
                'counts' => $counts,
                'total'  => array_sum($counts),
                'items'  => collect($items)->sortBy('start_date')->values()->all(),
            ];
        })
            ->filter(fn ($row) => $row['total'] > 0)
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
                'request_count' => $requests->count(),
            ],
            'ready' => true,
        ];
    }

    /** Minggu tidak dihitung sebagai hari cuti terpakai (Sabtu tetap dihitung, dikonfirmasi
     * user - beda dari perhitungan hari kerja lain di sistem ini yang mengecualikan Sabtu+Minggu). */
    private function countDaysExcludingSunday(Carbon $start, Carbon $end): int
    {
        $days = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! $date->isSunday()) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * Mode "table" - histori flat semua pengajuan cuti approved, terbaru (Awal Cuti) di atas.
     * Sengaja TIDAK diikat date_from/date_to (itu punya mode matrix) - ini memang histori penuh,
     * company+search saja yang membatasi.
     */
    public function getHistoryProperty()
    {
        return AttendanceRequestLeaveDetails::query()
            ->whereHas('request', function ($q) {
                $q->where('type', 'leave')
                    ->where('status', 'approved')
                    ->when($this->companyId, fn ($q2) => $q2->whereHas('user', fn ($q3) => $q3->where('company_id', $this->companyId)));
            })
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);
                $q->whereHas('request.user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"));
            })
            ->with([
                'request.user.company',
                'request.user.profile.jobPosition',
                'request.user.profile.orgStructure.parent.parent',
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->appends([
                'mode' => $this->mode,
                'companyId' => $this->companyId,
                'search' => $this->search,
            ]);
    }

    public function render()
    {
        return view('livewire.presensi.cuti-recap-page');
    }
}
