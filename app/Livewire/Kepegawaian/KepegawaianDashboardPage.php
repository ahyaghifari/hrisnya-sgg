<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\UserTransitionStatusEnum;
use App\Models\Employee\EmployeeNakesDocument;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserTransition;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard ringkas modul Kepegawaian — MVP: headcount + kelengkapan data + perubahan status
 * pending + kekosongan jabatan (data yang sudah bersih/siap pakai). Tiap tile bisa diklik buat
 * lihat detail lewat modal, bukan cuma angka mentah.
 */
#[Layout('components.layout', ['title' => 'Dashboard Kepegawaian'])]
class KepegawaianDashboardPage extends Component
{
    public ?string $companyId = null;
    public ?string $activeModal = null;
    public ?string $selectedPositionName = null;

    /** Field inti profil yang dipakai sebagai tolok ukur "data karyawan lengkap" — dokumen
     * kependudukan, kontak, dan rekening, yang paling sering dibutuhkan HC/payroll tapi
     * gampang kelewat diisi. Karyawan tanpa profil sama sekali dihitung 0%, bukan dikecualikan. */
    public const COMPLETENESS_FIELDS = [
        'no_nik' => 'NIK',
        'no_kk' => 'No. KK',
        'no_bpjs_kes' => 'BPJS Kesehatan',
        'address' => 'Alamat',
        'phone' => 'No. HP',
        'bank_account_number' => 'Rekening Bank',
        'date_of_birth' => 'Tanggal Lahir',
    ];

    /** Field kelengkapan terisi? Foto sengaja tidak dihitung (instruksi user 2026-10-13). */
    private function completenessFilled(User $user, string $field): bool
    {
        return filled($user->profile?->{$field});
    }

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function openModal(string $modal): void
    {
        $this->activeModal = $modal;
    }

    public function openPositionModal(string $name): void
    {
        $this->selectedPositionName = $name;
        $this->activeModal = 'position';
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
        $this->selectedPositionName = null;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    protected function scopedEmployees()
    {
        return User::query()->when($this->companyId, fn ($q) => $q->whereCompany($this->companyId));
    }

    public function getTotalActiveEmployeesProperty(): int
    {
        return $this->scopedEmployees()->count();
    }

    /** Buat modal Total Karyawan — breakdown per departemen. */
    public function getHeadcountByDepartmentProperty()
    {
        return $this->scopedEmployees()
            ->whereHas('profile.jobPosition.orgStructure')
            ->with('profile.jobPosition.orgStructure')
            ->get()
            ->groupBy(fn (User $u) => $u->profile?->jobPosition?->orgStructure?->name ?? '-')
            ->map(fn ($group, $name) => ['name' => $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();
    }

    public function getNewEmployeesThisMonthProperty(): int
    {
        return $this->scopedEmployees()
            ->whereHas('profile', fn ($q) => $q->whereBetween('joining_date', [now()->startOfMonth(), now()->endOfMonth()]))
            ->count();
    }

    /** Buat modal Karyawan Baru — daftar lengkap yang join bulan ini. */
    public function getNewEmployeesListProperty()
    {
        return $this->scopedEmployees()
            ->whereHas('profile', fn ($q) => $q->whereBetween('joining_date', [now()->startOfMonth(), now()->endOfMonth()]))
            ->with('profile.jobPosition')
            ->orderByDesc('id')
            ->get();
    }

    /** Buat panel & modal Perubahan Status — daftar ringkas (panel: 5, modal: 20). */
    public function getPendingTransitionsProperty()
    {
        return UserTransition::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->where('status', UserTransitionStatusEnum::PENDING->value)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function getPendingTransitionsForModalProperty()
    {
        return UserTransition::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->where('status', UserTransitionStatusEnum::PENDING->value)
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();
    }

    public function getPendingTransitionsCountProperty(): int
    {
        return UserTransition::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->where('status', UserTransitionStatusEnum::PENDING->value)
            ->count();
    }

    protected function jobPositionVacancies()
    {
        return JobPositions::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->withCount('userAssignments')
            ->get()
            ->filter(fn (JobPositions $jp) => $jp->manpower > $jp->user_assignments_count)
            ->map(fn (JobPositions $jp) => [
                'name' => $jp->name,
                'quota' => $jp->manpower,
                'filled' => $jp->user_assignments_count,
                'vacant' => $jp->manpower - $jp->user_assignments_count,
            ])
            ->sortByDesc('vacant')
            ->values();
    }

    public function getVacantPositionsCountProperty(): int
    {
        return $this->jobPositionVacancies()->count();
    }

    /** Buat modal Jabatan Kurang Orang — daftar jabatan + kuota vs terisi. */
    public function getVacantPositionsListProperty()
    {
        return $this->jobPositionVacancies();
    }

    /** Label masalah dokumen nakes, urut tampil. key => label */
    public const NAKES_ISSUES = [
        'str_missing' => 'STR belum diunggah',
        'str_date' => 'STR tanggal belum diisi',
        'sip_missing' => 'SIP belum diunggah',
        'sip_date' => 'SIP tanggal belum diisi',
        'sip_expired' => 'SIP habis masa berlaku',
    ];

    /**
     * Kelengkapan dokumen karyawan nakes (jabatan aktif is_nakes), dokumen AKTIF saja:
     * - STR: belum diunggah; atau tanggalnya belum diisi (STR seumur hidup = nomor terisi & tanggal kosong, aman).
     * - SIP: belum diunggah; tanggal mulai/sampai belum diisi; atau sudah lewat berlaku_sampai.
     *
     * @return array{counts: array<string,int>, rows: \Illuminate\Support\Collection, total: int, percent: int}
     */
    public function getNakesCompletenessProperty(): array
    {
        $users = $this->scopedEmployees()
            ->whereHas('activeJobPosition.jobPosition', fn ($q) => $q->where('is_nakes', true))
            ->with('employee')
            ->get();

        $docs = EmployeeNakesDocument::whereIn('employee_id', $users->pluck('employee.id')->filter())
            ->where('is_active', true)->get()->groupBy('employee_id');

        $today = now()->startOfDay();
        $counts = array_fill_keys(array_keys(self::NAKES_ISSUES), 0);
        $okItems = 0; // butir terpenuhi (5 per nakes: STR ada/tanggal, SIP ada/tanggal/berlaku) buat persentase

        $rows = $users->map(function (User $user) use ($docs, $today, &$counts, &$okItems) {
            $mine = $docs->get($user->employee?->id, collect())->keyBy('type');
            $str = $mine->get('STR');
            $sip = $mine->get('SIP');
            $issues = [];

            if (! $str) {
                $issues[] = 'str_missing';
            } elseif (! filled($str->nomor) && ! $str->berlaku_sampai) { // data lama: file saja
                $issues[] = 'str_date';
            }

            if (! $sip) {
                $issues[] = 'sip_missing';
            } elseif (! $sip->berlaku_mulai || ! $sip->berlaku_sampai) {
                $issues[] = 'sip_date';
            } elseif ($sip->berlaku_sampai->lt($today)) {
                $issues[] = 'sip_expired';
            }

            foreach ($issues as $i) {
                $counts[$i]++;
            }
            // Butir yang bergantung dokumen (tanggal/berlaku) ikut belum terpenuhi bila dokumennya belum ada.
            $okItems += 5 - count(array_unique(array_merge(
                $issues,
                $str ? [] : ['str_date'],
                $sip ? [] : ['sip_date', 'sip_expired'],
                in_array('sip_date', $issues, true) ? ['sip_expired'] : [], // tanggal kosong: berlaku tak bisa dinilai
            )));

            return ['user' => $user, 'issues' => $issues];
        })->filter(fn (array $r) => $r['issues'] !== [])
            ->sortByDesc(fn (array $r) => count($r['issues']))
            ->values();

        return [
            'counts' => $counts,
            'rows' => $rows,
            'total' => $users->count(),
            'percent' => $users->isEmpty() ? 0 : (int) round($okItems / ($users->count() * 5) * 100),
        ];
    }

    public function getDataCompletenessPercentProperty(): int
    {
        $employees = $this->scopedEmployees()->with('profile')->get();

        if ($employees->isEmpty()) {
            return 0;
        }

        $fieldCount = count(self::COMPLETENESS_FIELDS);

        $total = $employees->sum(function (User $user) use ($fieldCount) {
            if (! $user->profile) {
                return 0;
            }

            $filled = collect(array_keys(self::COMPLETENESS_FIELDS))
                ->filter(fn (string $field) => $this->completenessFilled($user, $field))
                ->count();

            return $filled / $fieldCount;
        });

        return (int) round($total / $employees->count() * 100);
    }

    /** Buat modal Kelengkapan Data — karyawan dengan field masih kosong, diurutkan yang paling
     * tidak lengkap dulu, plus daftar nama field yang belum diisi per orang. */
    public function getIncompleteEmployeesProperty()
    {
        return $this->scopedEmployees()
            ->with('profile')
            ->get()
            ->map(function (User $user) {
                $missing = collect(self::COMPLETENESS_FIELDS)
                    ->reject(fn (string $label, string $field) => $user->profile && $this->completenessFilled($user, $field))
                    ->values();

                return ['user' => $user, 'missing' => $missing];
            })
            ->filter(fn (array $row) => $row['missing']->isNotEmpty())
            ->sortByDesc(fn (array $row) => $row['missing']->count())
            ->take(20)
            ->values();
    }

    /** Top 8 jabatan dengan karyawan aktif terbanyak — buat bar distribusi headcount. */
    public function getHeadcountByPositionProperty()
    {
        $rows = User::query()
            ->when($this->companyId, fn ($q) => $q->whereCompany($this->companyId))
            ->whereHas('profile.jobPosition')
            ->with('profile.jobPosition')
            ->get()
            ->groupBy(fn (User $u) => $u->profile?->jobPosition?->name ?? '-')
            ->map(fn ($group, $name) => ['name' => $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        $max = $rows->max('count') ?: 1;

        return $rows->map(fn ($row) => $row + ['percent' => (int) round($row['count'] / $max * 100)]);
    }

    /** Buat modal per-bar Distribusi Karyawan per Jabatan. */
    public function getEmployeesInSelectedPositionProperty()
    {
        if (! $this->selectedPositionName) {
            return collect();
        }

        return $this->scopedEmployees()
            ->whereHas('profile.jobPosition', fn ($q) => $q->where('name', $this->selectedPositionName))
            ->with('profile.jobPosition')
            ->get();
    }

    public function render()
    {
        return view('livewire.kepegawaian.kepegawaian-dashboard-page');
    }
}
