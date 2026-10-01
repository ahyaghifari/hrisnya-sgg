<?php

namespace App\Livewire\Presensi;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Concerns\FiltersByDepartmentDivision;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Services\Attendance\AttendancePeriodResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rekap seluruh pengajuan Izin (APPROVED) dalam 1 periode, dipecah per kategori
 * PermissionTypeCategoryEnum (Terlambat Masuk/Pulang Cepat/Izin Seharian/Meninggalkan Jam
 * Kerja) — matrix karyawan x kategori. Periode ikut cutoff company (AttendancePeriodResolver),
 * konsisten dengan kuota Izin di halaman approval/pengajuan. Jenis izin yang belum dikategorikan
 * tetap ikut dihitung di kolom "Lainnya" supaya benar-benar "seluruh izin", bukan cuma yang
 * kena kuota.
 */
#[Layout('components.layout', ['title' => 'Rekap Izin'])]
class IzinRecapPage extends Component
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
     *     categories: array<string, string>,
     *     rows: array<int, array{user: \App\Models\User, counts: array<string, int>, total: int}>,
     *     overall: array{counts: array<string, int>, total: int, employee_count: int},
     *     ready: bool
     * }
     */
    public function getRecapProperty(): array
    {
        $categories = collect(PermissionTypeCategoryEnum::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();

        $emptyCounts = collect($categories)->mapWithKeys(fn ($label, $key) => [$key => 0])->all() + ['lainnya' => 0];

        // Belum pilih Dari/Sampai Tanggal — jangan query, tampilkan state kosong dulu.
        if (! $this->date_from || ! $this->date_to) {
            return [
                'categories' => $categories,
                'rows' => [],
                'overall' => ['counts' => $emptyCounts, 'total' => 0, 'employee_count' => 0],
                'ready' => false,
            ];
        }

        $details = AttendanceRequestPermissionDetails::query()
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('type', 'permission')
                ->where('status', 'approved')
                ->whereHas('user', function ($q2) {
                    $q2->when($this->companyId, fn ($q3) => $q3->where('company_id', $this->companyId));
                    $this->applyDepartmentDivisionFilter($q2);
                }))
            ->with(['attendanceRequest.user', 'attendanceRequest.user.profile.jobPosition.orgStructure.parent.parent'])
            ->get();

        $grouped = $details
            ->filter(fn ($d) => $d->attendanceRequest?->user)
            ->groupBy(fn ($d) => $d->attendanceRequest->user_id);

        $rows = $grouped->map(function ($group) use ($emptyCounts, $categories) {
            $user = $group->first()->attendanceRequest->user;
            $counts = $emptyCounts;

            // Rincian per tanggal — dipakai modal "kapan aja izinnya diajukan" (klik nama
            // karyawan), bukan cuma angka rekap di tabel.
            $items = $group->map(fn ($detail) => [
                'date' => $detail->date,
                'end_date' => $detail->end_date,
                'total_days' => $detail->total_days,
                'type_name' => $detail->permission_type,
                'category_label' => $categories[$detail->category] ?? 'Lainnya',
                'start_time' => $detail->start_time,
                'end_time' => $detail->end_time,
                'purpose' => $detail->purpose,
            ])->sortBy('date')->values()->all();

            foreach ($group as $detail) {
                $category = $detail->category;
                $key = ($category && array_key_exists($category, $counts)) ? $category : 'lainnya';
                $counts[$key]++;
            }

            return [
                'user' => $user,
                'counts' => $counts,
                'total' => $group->count(),
                'items' => $items,
            ];
        })
            ->sortByDesc('total')
            ->values();

        // Total keseluruhan (kartu ringkasan) dihitung dari SEMUA baris sebelum difilter cari
        // nama — supaya angka overview tidak diam-diam berubah cuma karena user lagi ngetik cari.
        $overallCounts = $emptyCounts;
        foreach ($rows as $row) {
            foreach ($row['counts'] as $key => $value) {
                $overallCounts[$key] += $value;
            }
        }

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
                'employee_count' => $grouped->count(),
            ],
            'ready' => true,
        ];
    }

    public function render()
    {
        return view('livewire.presensi.izin-recap-page');
    }
}
