<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ShiftType;
use App\Livewire\Concerns\FiltersByDepartmentDivision;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Services\Attendance\AttendancePeriodResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rekap Extra Shift (APPROVED) dalam 1 periode — matriks karyawan x jenis shift tujuan, sama
 * gaya dengan LemburRecapPage. Yang dihitung = jumlah kejadian. Dua tab terpisah: `extra_shift`
 * (dibayar jasa) dan `double_shift` (akibat tukar shift bentrok, tidak dibayar jasa) - tab
 * Extra Shift sejajar dengan yang dibayar ExtraShiftCalculator. Nominal jasa sengaja tidak
 * ditampilkan (data Payroll, beda gerbang dari permission rekap absensi).
 *
 * Butuh DUA permission sekaligus: rekap absensi + lihat Extra Shift.
 */
#[Layout('components.layout', ['title' => 'Rekap Extra Shift'])]
class ExtraShiftRecapPage extends Component
{
    use GatesCompanyFilterBySystem, FiltersByDepartmentDivision;

    public ?string $companyId = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $selectedUserId = null;
    public string $search = '';

    // extra_shift | double_shift - nilai = ExtraShiftAssignment::category
    #[Url]
    public string $tab = ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->can(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value)
                && $user->can('viewAny', ExtraShiftAssignment::class),
            403
        );

        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_ABSENSI);

        if ($this->companyId === null) {
            $this->companyId = $user->company_id;
        }

        $this->normalizeTab();

        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve(now(), $this->companyId);
        $this->date_from = $periodStart->toDateString();
        $this->date_to = $periodEnd->toDateString();
    }

    public function updatedTab(): void
    {
        $this->normalizeTab();
        $this->selectedUserId = null;
    }

    /** ?tab= dari URL bisa berisi nilai apa saja - selain 2 kategori valid jatuh ke Extra Shift. */
    private function normalizeTab(): void
    {
        if (! in_array($this->tab, [ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT, ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT], true)) {
            $this->tab = ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT;
        }
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

    /** Label jenis shift (kolom matriks); nilai di luar enum (data lama) jatuh ke ucfirst. */
    public static function shiftTypeLabel(string $type): string
    {
        return ShiftType::tryFrom($type)?->getLabel() ?? ucfirst($type);
    }

    /**
     * @return array{
     *     types: array<string>,
     *     rows: array<int, array{user: \App\Models\User, counts: array<string, int>, total: int, items: array, bar_percent: int}>,
     *     overall: array{counts: array<string, int>, total: int, employee_count: int},
     *     tab_totals: array<string, int>,
     *     ready: bool
     * }
     */
    public function getRecapProperty(): array
    {
        $empty = ['types' => [], 'rows' => [], 'overall' => ['counts' => [], 'total' => 0, 'employee_count' => 0], 'tab_totals' => [ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT => 0, ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT => 0], 'ready' => false];

        if (! $this->date_from || ! $this->date_to) {
            return $empty;
        }

        // Kedua kategori diambil sekaligus supaya badge jumlah di kedua tab selalu terisi;
        // yang ditampilkan di kartu/tabel hanya kategori tab aktif.
        $all = ExtraShiftAssignment::query()
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->where('status', 'approved')
            ->whereHas('user', function ($q) {
                $q->when($this->companyId, fn ($q2) => $q2->where('company_id', $this->companyId));
                $this->applyDepartmentDivisionFilter($q);
            })
            ->with(['user', 'shiftType', 'attendanceRecord'])
            ->get()
            ->filter(fn ($a) => $a->user);

        $tabTotals = [
            ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT => $all->where('category', ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT)->count(),
            ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT => $all->where('category', ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT)->count(),
        ];

        $assignments = $all->where('category', $this->tab);

        $types = $assignments->map(fn ($a) => $a->shiftType?->shift_type ?: 'lainnya')->unique()->sort()->values()->all();
        $emptyCounts = collect($types)->mapWithKeys(fn ($t) => [$t => 0])->all();

        $rows = $assignments->groupBy('user_id')->map(function ($group) use ($emptyCounts) {
            $counts = $emptyCounts;

            $items = $group->map(function ($a) use (&$counts) {
                $type = $a->shiftType?->shift_type ?: 'lainnya';
                $counts[$type]++;

                return [
                    'date'       => $a->date,
                    'type'       => $type,
                    'shift_name' => $a->shiftType?->name ?? '-',
                    'start_time' => $a->shiftType?->start_time,
                    'end_time'   => $a->shiftType?->end_time,
                    'source'     => $a->source,
                    'check_in'   => $a->attendanceRecord?->check_in_time,
                    'check_out'  => $a->attendanceRecord?->check_out_time,
                ];
            })->sortBy('date')->values()->all();

            return [
                'user'   => $group->first()->user,
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
            ],
            'tab_totals' => $tabTotals,
            'ready' => true,
        ];
    }

    public function render()
    {
        return view('livewire.presensi.extra-shift-recap-page');
    }
}
