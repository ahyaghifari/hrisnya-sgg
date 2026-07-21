<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
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
    public ?string $companyId = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $selectedUserId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);

        if (! PermissionHelper::hasGlobalRole(Auth::user())) {
            $this->companyId = Auth::user()->company_id;
        }

        // Filter tanggal sengaja kosong dulu — user pilih sendiri Dari/Sampai Tanggal, bukan
        // otomatis ke periode berjalan (AttendancePeriodResolver masih dipakai kalau nanti
        // mau dibalikin defaultnya, cuma tidak di-pre-fill di sini untuk saat ini).
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
                ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId))))
            ->with(['attendanceRequest.user', 'permissionType'])
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
                'type_name' => $detail->permission_type ?? $detail->permissionType?->name,
                'category_label' => $categories[$detail->permissionType?->category] ?? 'Lainnya',
                'start_time' => $detail->start_time,
                'end_time' => $detail->end_time,
            ])->sortBy('date')->values()->all();

            foreach ($group as $detail) {
                $category = $detail->permissionType?->category;
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
            ->values()
            ->all();

        // Card perhitungan keseluruhan — total per kategori + grand total, dijumlah dari
        // seluruh baris karyawan di atas (bukan query terpisah, biar selalu konsisten).
        $overallCounts = $emptyCounts;
        foreach ($rows as $row) {
            foreach ($row['counts'] as $key => $value) {
                $overallCounts[$key] += $value;
            }
        }

        return [
            'categories' => $categories,
            'rows' => $rows,
            'overall' => [
                'counts' => $overallCounts,
                'total' => array_sum($overallCounts),
                'employee_count' => count($rows),
            ],
            'ready' => true,
        ];
    }

    public function render()
    {
        return view('livewire.kepegawaian.izin-recap-page');
    }
}
