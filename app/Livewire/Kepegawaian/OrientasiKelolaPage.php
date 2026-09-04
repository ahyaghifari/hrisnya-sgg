<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\OrientationSubmission;
use App\Models\User\UserOrientation;
use App\Services\OrientationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sisi HR / atasan untuk modul Orientasi — 3 tab dalam satu halaman:
 *  - pengajuan : daftar pengajuan penilaian + keputusan (klik ke OrientasiDetailPage)
 *  - periode   : ledger user_orientations (dibuat lewat Rekrutmen / perpanjangan)
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

    /** semua | menunggu | dinilai | selesai */
    #[Url]
    public string $status = 'semua';

    /** pengajuan | periode | indikator */
    #[Url]
    public string $tab = 'pengajuan';

    /** @var array<int, array{name: string, group_label: ?string, max_score: float}> */
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

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
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

    public function getSubmissionsProperty()
    {
        $map = [
            'menunggu' => ['menunggu_persetujuan'],
            'dinilai' => ['dinilai'],
            'selesai' => ['selesai'],
        ];

        return OrientationSubmission::query()
            ->with(['user.company', 'decidedBy'])
            ->where('status', '!=', 'draft')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when(isset($map[$this->status]), fn ($q) => $q->whereIn('status', $map[$this->status]))
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%"));
            })
            ->orderByDesc('submitted_at')
            ->paginate(12)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search, 'status' => $this->status, 'tab' => $this->tab]);
    }

    /** Periode orientasi (user_orientations) — ledger, dibuat lewat Rekrutmen / perpanjangan. */
    public function getPeriodsProperty()
    {
        return UserOrientation::query()
            ->with(['user.company'])
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%"));
            })
            ->orderByDesc('start_date')
            ->paginate(12)
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
        $this->criteriaRows[] = ['name' => '', 'group_label' => null, 'max_score' => 5];
    }

    public function removeCriterion(int $index): void
    {
        abort_unless($this->canEditCriteria, 403);
        unset($this->criteriaRows[$index]);
        $this->criteriaRows = array_values($this->criteriaRows);
    }

    public function saveCriteria(): void
    {
        abort_unless($this->canEditCriteria, 403);

        $this->validate([
            'criteriaRows.*.name' => 'required|string|max:255',
            'criteriaRows.*.max_score' => 'required|numeric|min:1',
        ], [], [
            'criteriaRows.*.name' => 'nama indikator',
            'criteriaRows.*.max_score' => 'skor maksimal',
        ]);

        $clean = collect($this->criteriaRows)
            ->map(fn ($r) => [
                'name' => trim($r['name']),
                'group_label' => $r['group_label'] ? trim($r['group_label']) : null,
                'max_score' => (float) $r['max_score'],
            ])
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
