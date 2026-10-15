<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Exports\Presensi\RecapDetailExport;
use App\Enum\ShiftType;
use App\Livewire\Concerns\FiltersByDepartmentDivision;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Services\Attendance\AttendancePeriodResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rekap Extra Shift (APPROVED) dalam 1 periode — matriks karyawan x jenis, sama gaya dengan
 * LemburRecapPage: 2 kolom jenis (`extra_shift` dibayar jasa, `double_shift` akibat tukar shift
 * bentrok, tidak dibayar jasa) + Total, tanpa tab. Yang dihitung = jumlah kejadian; kolom Extra
 * Shift tetap terpisah dari Double Shift supaya sejajar dengan yang dibayar ExtraShiftCalculator.
 * Nominal jasa sengaja tidak ditampilkan (data Payroll, beda gerbang dari permission rekap absensi).
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

        $categories = $this->recap['categories'];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new RecapDetailExport(
                $this->recap['rows'],
                ['Tanggal', 'Kategori', 'Shift', 'Waktu', 'Jadwal Reguler', 'Presensi', 'Keterangan'],
                fn ($i) => [
                    RecapDetailExport::date($i['date']),
                    $categories[$i['category']] ?? $i['category'],
                    $i['shift_name'],
                    RecapDetailExport::time($i['start_time']) . ' - ' . RecapDetailExport::time($i['end_time']),
                    $i['regular'],
                    RecapDetailExport::presensi($i['check_in'], $i['check_out']),
                    $i['reason'] ?: '-',
                ],
                'Rekap Extra Shift',
            ),
            "rekap-extra-shift_{$this->date_from}_{$this->date_to}.xlsx",
        );
    }

    /** Label jenis shift (modal rincian); nilai di luar enum (data lama) jatuh ke ucfirst. */
    public static function shiftTypeLabel(string $type): string
    {
        return ShiftType::tryFrom($type)?->getLabel() ?? ucfirst($type);
    }

    /**
     * @return array{
     *     categories: array<string, string>,
     *     rows: array<int, array{user: \App\Models\User, counts: array<string, int>, total: int, items: array, bar_percent: int}>,
     *     overall: array{counts: array<string, int>, total: int, employee_count: int},
     *     ready: bool
     * }
     */
    public function getRecapProperty(): array
    {
        $categories = [
            ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT => 'Extra Shift',
            ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT => 'Double Shift',
        ];
        $emptyCounts = collect($categories)->mapWithKeys(fn ($label, $key) => [$key => 0])->all();

        if (! $this->date_from || ! $this->date_to) {
            return [
                'categories' => $categories,
                'rows' => [],
                'overall' => ['counts' => $emptyCounts, 'total' => 0, 'employee_count' => 0],
                'ready' => false,
            ];
        }

        $assignments = ExtraShiftAssignment::query()
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->where('status', 'approved')
            ->whereHas('user', function ($q) {
                $q->when($this->companyId, fn ($q2) => $q2->whereCompany($this->companyId));
                $this->applyDepartmentDivisionFilter($q);
            })
            ->with(['user', 'user.profile.jobPosition.orgStructure.parent.parent', 'shiftType', 'attendanceRecord', 'coveringUser', 'createdBy',
                'originShiftSwapRequest' => fn ($q) => $q->withoutGlobalScope('company')->with(['requester', 'target']),
            ])
            ->get()
            ->filter(fn ($a) => $a->user);

        // Jadwal reguler di tanggal extra shift: baris off_day = libur, tidak ada baris = kosong.
        // Scope company dibuka karena viewer bisa lintas company (pola ShiftSwapRecapPage).
        $schedules = ScheduleShiftAssignment::withoutGlobalScope('company')
            ->with('shiftType')
            ->whereIn('user_id', $assignments->pluck('user_id')->unique())
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->get()
            ->keyBy(fn ($sc) => $sc->user_id . '|' . substr((string) $sc->date, 0, 10));
        $rows = $assignments->groupBy('user_id')->map(function ($group) use ($emptyCounts, $schedules) {
            $counts = $emptyCounts;

            $items = $group->map(function ($a) use (&$counts, $schedules) {
                $category = array_key_exists($a->category, $counts) ? $a->category : ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT;
                $counts[$category]++;

                return [
                    'date'       => $a->date,
                    'category'   => $category,
                    'type'       => $a->shiftType?->shift_type ?: 'lainnya',
                    'shift_name' => $a->shiftType?->name ?? '-',
                    'start_time' => $a->shiftType?->start_time,
                    'end_time'   => $a->shiftType?->end_time,
                    'source'     => $a->source,
                    'check_in'   => $a->attendanceRecord?->check_in_time,
                    'check_out'  => $a->attendanceRecord?->check_out_time,
                    'regular'    => (function () use ($schedules, $a) {
                        $sc = $schedules[$a->user_id . '|' . substr((string) $a->date, 0, 10)] ?? null;

                        return match (true) {
                            ! $sc => 'Kosong',
                            $sc->off_day || ! $sc->shiftType => 'Libur',
                            default => $sc->shiftType->name,
                        };
                    })(),                    'reason'     => $a->reason,
                    'covering'   => $a->coveringUser?->name,
                    'assigned_by' => $a->source === 'assigned' ? $a->createdBy?->name : null,
                    'swap'       => ($sw = $a->originShiftSwapRequest) ? [
                        'kind'      => $sw->swap_kind === 'reciprocal_off' ? 'tukar libur' : 'tukar shift',
                        'requester' => $sw->requester?->name,
                        'target'    => $sw->target?->name,
                        'date'      => $sw->req_date,
                        'target_date' => $sw->target_date,
                        'notes'     => $sw->notes,
                    ] : null,
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

        // Total keseluruhan dihitung dari SEMUA baris sebelum difilter cari nama — supaya angka
        // overview tidak diam-diam berubah cuma karena user lagi ngetik cari.
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
            'categories' => $categories,
            'rows' => $rows,
            'overall' => [
                'counts' => $overallCounts,
                'total' => array_sum($overallCounts),
                'employee_count' => $employeeCount,
            ],
            'ready' => true,
        ];
    }

    public function render()
    {
        return view('livewire.presensi.extra-shift-recap-page');
    }
}
