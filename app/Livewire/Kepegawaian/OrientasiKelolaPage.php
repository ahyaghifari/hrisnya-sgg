<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Models\Master\Companies;
use App\Models\OrientationSubmission;
use App\Models\User;
use App\Services\OrientationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sisi HR / atasan untuk modul Orientasi — 3 tab dalam satu halaman:
 *  - pengajuan : karyawan yang SAAT INI berstatus Orientasi — 1 baris/orang, gabungan periode
 *    (mulai–selesai) + status pengajuan penilaian + keputusan + aksi.
 *  - riwayat   : karyawan yang SUDAH lulus/pindah status (kontrak/tetap) tapi punya riwayat
 *    periode orientasi — outcome terakhirnya.
 *  - indikator : kelola indikator penilaian per company (Setting `orientation_criteria`)
 * Beda dari /orientasi yang self-service milik karyawan sendiri.
 */
#[Layout('components.layout', ['title' => 'Orientasi'])]
class OrientasiKelolaPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** pengajuan | riwayat | indikator */
    #[Url]
    public string $tab = 'pengajuan';

    /** @var array<int, array{name: string}> */
    public array $criteriaRows = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', OrientationSubmission::class), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->loadCriteria();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();

        if ($tab === 'indikator') {
            $this->loadCriteria();
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
        $this->resetPage();
        $this->loadCriteria();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::orderBy('code')->pluck('name', 'id');
    }

    /** Query dasar: karyawan berdasarkan kategori employee_type-nya (orientasi vs bukan). */
    private function usersByOrientasiStatus(bool $currentlyOrientasi)
    {
        return User::query()
            ->when($this->companyId, fn ($q) => $q->whereCompany($this->companyId))
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->searchNameOrNip($s);
            })
            ->whereHas('employee.orientations')
            ->when($currentlyOrientasi,
                fn ($q) => $q->whereHas('profile.employeeType', fn ($q2) => $q2->where('category', EmployeeTypeCategory::ORIENTASI->value)),
                fn ($q) => $q->whereDoesntHave('profile.employeeType', fn ($q2) => $q2->where('category', EmployeeTypeCategory::ORIENTASI->value)),
            )
            ->with([
                'company',
                'profile.employeeType',
                'employee.orientations' => fn ($q) => $q->orderByDesc('start_date'),
                'orientationSubmissions' => fn ($q) => $q->with('decidedBy')->orderByDesc('created_at'),
            ])
            ->orderByName();
    }

    /** Tab "pengajuan" — karyawan yang SAAT INI berstatus Orientasi. */
    public function getCurrentOrientasiProperty()
    {
        return $this->usersByOrientasiStatus(true)
            ->paginate(15)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search, 'tab' => $this->tab]);
    }

    /** Tab "riwayat" — karyawan yang statusnya sudah BUKAN Orientasi (kontrak/tetap). */
    public function getRiwayatProperty()
    {
        return $this->usersByOrientasiStatus(false)
            ->paginate(15)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search, 'tab' => $this->tab]);
    }

    // ── Tab Indikator (eks OrientasiIndikatorPage) ─────────────────────────────

    public function getCanViewCriteriaProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value);
    }

    public function getCanEditCriteriaProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_KEPEGAWAIAN->value);
    }

    private function loadCriteria(): void
    {
        if ($this->canViewCriteria) {
            $this->criteriaRows = app(OrientationService::class)->criteria($this->companyId);
        }
    }

    public function addCriterion(): void
    {
        abort_unless($this->canEditCriteria, 403);
        $this->criteriaRows[] = ['name' => ''];
    }

    public function removeCriterion(int $index): void
    {
        abort_unless($this->canEditCriteria, 403);
        unset($this->criteriaRows[$index]);
        $this->criteriaRows = array_values($this->criteriaRows);
    }

    public function moveCriterionUp(int $index): void
    {
        abort_unless($this->canEditCriteria, 403);
        if ($index > 0 && isset($this->criteriaRows[$index])) {
            [$this->criteriaRows[$index - 1], $this->criteriaRows[$index]] = [$this->criteriaRows[$index], $this->criteriaRows[$index - 1]];
        }
    }

    public function moveCriterionDown(int $index): void
    {
        abort_unless($this->canEditCriteria, 403);
        if ($index < count($this->criteriaRows) - 1 && isset($this->criteriaRows[$index])) {
            [$this->criteriaRows[$index + 1], $this->criteriaRows[$index]] = [$this->criteriaRows[$index], $this->criteriaRows[$index + 1]];
        }
    }

    public function saveCriteria(): void
    {
        abort_unless($this->canEditCriteria, 403);

        $this->validate([
            'criteriaRows.*.name' => 'required|string|max:255',
        ], [], [
            'criteriaRows.*.name' => 'nama indikator',
        ]);

        $clean = collect($this->criteriaRows)
            ->map(fn ($r) => ['name' => trim($r['name'])])
            ->filter(fn ($r) => $r['name'] !== '')
            ->values()
            ->all();

        app(OrientationService::class)->saveCriteria($this->companyId, $clean);

        Notification::make()->title('Indikator orientasi tersimpan')->success()->send();
    }

    public function render()
    {
        return view('livewire.kepegawaian.orientasi-kelola-page');
    }
}
