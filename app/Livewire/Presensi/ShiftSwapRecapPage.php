<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Concerns\FiltersByDepartmentDivision;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Master\Companies;
use App\Models\Shift\ShiftSwapRequests;
use App\Services\Attendance\AttendancePeriodResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rekap Tukar Shift dalam 1 periode (berdasarkan req_date) - matriks karyawan x status
 * (Disetujui/Diproses/Ditolak/Dibatalkan) + Total, gaya sama ExtraShiftRecapPage. Tiap pengajuan
 * dihitung untuk DUA orang (pengaju & rekan) karena tukar shift menyentuh jadwal keduanya; baris
 * rincian di modal menandai perannya. Permission: rekap absensi (VIEW_REPORT_DATA_ABSENSI).
 */
#[Layout('components.layout', ['title' => 'Rekap Tukar Shift'])]
class ShiftSwapRecapPage extends Component
{
    use GatesCompanyFilterBySystem, FiltersByDepartmentDivision;

    public const STATUSES = [
        'approved' => 'Disetujui',
        'in_review' => 'Diproses',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    public ?string $companyId = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $selectedUserId = null;
    public string $search = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value), 403);

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

    /** Status di luar daftar (mis. 'pending' lama) dihitung sebagai Diproses. */
    private function normalizeStatus(string $status): string
    {
        return array_key_exists($status, self::STATUSES) ? $status : 'in_review';
    }

    /**
     * @return array{
     *     rows: array<int, array{user: \App\Models\User, counts: array<string, int>, total: int, items: array, bar_percent: int}>,
     *     overall: array{counts: array<string, int>, total: int, employee_count: int},
     *     ready: bool
     * }
     */
    public function getRecapProperty(): array
    {
        $emptyCounts = array_map(fn () => 0, self::STATUSES);

        if (! $this->date_from || ! $this->date_to) {
            return ['rows' => [], 'overall' => ['counts' => $emptyCounts, 'total' => 0, 'employee_count' => 0], 'ready' => false];
        }

        // Scope company global dibuka - difilter lewat company pengaju di bawah (pola ExtraShiftRecapPage).
        $swaps = ShiftSwapRequests::withoutGlobalScope('company')
            ->whereBetween('req_date', [$this->date_from, $this->date_to])
            ->whereHas('requester', function ($q) {
                $q->when($this->companyId, fn ($q2) => $q2->whereCompany($this->companyId));
                $this->applyDepartmentDivisionFilter($q);
            })
            ->with(['requester.profile.jobPosition.orgStructure.parent.parent', 'target.profile.jobPosition.orgStructure.parent.parent', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
            ->get();

        // 1 pengajuan -> 1 entri, atas nama pengaju saja (rekan tidak ditampilkan sebagai baris sendiri).
        $entries = $swaps->filter(fn ($s) => $s->requester)
            ->map(fn ($s) => ['user' => $s->requester, 'role' => 'Pengaju', 'partner' => $s->target?->name, 'swap' => $s]);

        $rows = $entries->groupBy(fn ($e) => $e['user']->id)->map(function ($group) use ($emptyCounts) {
            $counts = $emptyCounts;

            $items = $group->map(function ($e) use (&$counts) {
                $status = $this->normalizeStatus($e['swap']->status);
                $counts[$status]++;

                $reqShift = $e['swap']->reqSchedule?->shiftType?->name;
                $targetShift = $e['swap']->targetSchedule?->shiftType?->name;

                // ponytail: tukar tanggal sama yang sudah approved menukar shift_type di baris jadwal,
                // jadi nama dari relasi = shift SESUDAH tukar; dibalik lagi biar jadi shift awal.
                if ($status === 'approved' && $e['swap']->target_date?->equalTo($e['swap']->req_date)) {
                    [$reqShift, $targetShift] = [$targetShift, $reqShift];
                }

                return [
                    'date' => $e['swap']->req_date,
                    'target_date' => $e['swap']->target_date,
                    'status' => $status,
                    'role' => $e['role'],
                    'partner' => $e['partner'] ?? '-',
                    'kind' => $e['swap']->swap_kind === 'reciprocal_off' ? 'Tukar Libur' : 'Tukar Shift',
                    // same = tanggal sama, diff = tanggal beda, libur = tukar libur berpasangan (ambil libur teman).
                    'type' => $e['swap']->swap_kind === 'reciprocal_off' ? 'libur'
                        : ($e['swap']->target_date?->equalTo($e['swap']->req_date) ? 'same' : 'diff'),
                    'req_shift' => $reqShift,
                    'target_shift' => $targetShift,
                ];
            })->sortBy('date')->values()->all();

            return ['user' => $group->first()['user'], 'counts' => $counts, 'total' => array_sum($counts), 'items' => $items];
        })->sortByDesc('total')->values();

        // Kartu ringkasan menghitung PENGAJUAN (bukan orang), dihitung sebelum filter cari nama
        // supaya angka tidak berubah saat mengetik.
        $overallCounts = $emptyCounts;
        foreach ($swaps as $s) {
            $overallCounts[$this->normalizeStatus($s->status)]++;
        }
        $employeeCount = $rows->count();

        $search = mb_strtolower(trim($this->search));
        if ($search !== '') {
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower($row['user']->name), $search)
                || str_contains(mb_strtolower($row['user']->nip ?? ''), $search));
        }

        $maxTotal = $rows->max('total') ?: 1;
        $rows = $rows->map(fn ($row) => $row + ['bar_percent' => (int) round($row['total'] / $maxTotal * 100)])->values()->all();

        return [
            'rows' => $rows,
            'overall' => ['counts' => $overallCounts, 'total' => array_sum($overallCounts), 'employee_count' => $employeeCount],
            'ready' => true,
        ];
    }

    public function render()
    {
        return view('livewire.presensi.shift-swap-recap-page');
    }
}
