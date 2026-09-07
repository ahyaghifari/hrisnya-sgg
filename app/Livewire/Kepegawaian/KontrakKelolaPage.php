<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\ContractDecisionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Helpers\PermissionHelper;
use App\Models\ContractSubmission;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContractExtensionService;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sisi HR untuk modul Perpanjangan Kontrak — 1 halaman, mirip OrientasiKelolaPage. 2 tab:
 *  - aktif  : karyawan kategori Kontrak dengan kontrak berjalan — 1 baris/orang, periode +
 *    sisa hari + kolom "Mengajukan?" (centang = pengajuan diajukan/selesai, strip = belum
 *    masuk jendela pengajuan) + keputusan + tombol tinjau.
 *  - riwayat: pengajuan perpanjangan yang sudah selesai/ditolak.
 *
 * Peninjauan pengajuan (dulu KontrakDetailPage) sekarang inline di panel bawah: keputusan
 * `perpanjang` (+ durasi) atau `tidak_perpanjang` + catatan, atau minta revisi dokumen.
 */
#[Layout('components.layout', ['title' => 'Perpanjangan Kontrak'])]
class KontrakKelolaPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** aktif | riwayat */
    #[Url]
    public string $tab = 'aktif';

    /** Pengajuan yang sedang ditinjau di panel bawah (deep-link dari PendingApprovalsCard). */
    #[Url]
    public ?string $openId = null;

    public ?string $reviewDecision = null;
    public ?string $reviewNote = null;
    public ?int $reviewDurasi = null;
    public ?string $revisionNote = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', ContractSubmission::class), 403);

        if (! Auth::user()->canFilterByCompany() || $this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }

        if ($this->openId !== null) {
            $this->openReview($this->openId);
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
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

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::orderBy('code')->pluck('name', 'id');
    }

    public function getKontrakKaryawanProperty()
    {
        return User::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->where(fn ($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%"));
            })
            ->whereHas('profile.employeeType', fn ($q) => $q->where('category', EmployeeTypeCategory::KONTRAK->value))
            ->whereHas('contracts')
            ->with([
                'company',
                'currentContract',
                'contractSubmissions' => fn ($q) => $q->with('decidedBy')->latest(),
            ])
            ->orderBy('name')
            ->paginate(15)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search, 'tab' => $this->tab]);
    }

    public function getRiwayatProperty()
    {
        return ContractSubmission::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->whereIn('status', ['selesai', 'ditolak'])
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('user', function ($q2) {
                $s = trim($this->search);
                $q2->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%");
            }))
            ->with(['user', 'decidedBy', 'newContract'])
            ->latest('decided_at')
            ->paginate(15)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search, 'tab' => $this->tab]);
    }

    /**
     * Kolom "Mengajukan?" tab aktif: 'yes' (centang) kalau pengajuan terakhir diajukan/selesai,
     * 'strip' kalau belum masuk jendela pengajuan (end - PENGAJUAN_KONTRAK_LEAD_HARI), 'no' sisanya.
     */
    public function submissionMark(User $u): string
    {
        $sub = $u->contractSubmissions->first();
        if ($sub && in_array($sub->status, ['diajukan', 'selesai'], true)) {
            return 'yes';
        }

        $end = $u->currentContract?->contract_end_date;
        if ($end) {
            $lead = $this->leadDaysFor($u->company_id);
            if ($lead > 0 && now()->lt(Carbon::parse($end)->subDays($lead)->startOfDay())) {
                return 'strip';
            }
        }

        return 'no';
    }

    private array $leadDaysCache = [];

    private function leadDaysFor(?string $companyId): int
    {
        return $this->leadDaysCache[$companyId] ??= (int) Setting::resolve(
            KepegawaianSettingEnum::PENGAJUAN_KONTRAK_LEAD_HARI->value,
            $companyId,
            default: KepegawaianSettingEnum::PENGAJUAN_KONTRAK_LEAD_HARI->default(),
        );
    }

    // ── Peninjauan inline ────────────────────────────────────────────────────

    public function getOpenSubmissionProperty(): ?ContractSubmission
    {
        if (! $this->openId) {
            return null;
        }

        $record = ContractSubmission::with([
            'user.profile', 'user.currentContract', 'userContract', 'newContract', 'documents', 'decidedBy',
        ])->find($this->openId);

        return $record && $this->canAccess($record, Auth::user()) ? $record : null;
    }

    public function openReview(string $submissionId): void
    {
        $this->openId = $submissionId;
        $this->reset('reviewDecision', 'reviewNote', 'revisionNote');
        $this->resetErrorBag();

        $record = $this->openSubmission;
        if (! $record) {
            $this->openId = null;

            return;
        }

        $this->reviewDurasi = $record->usulan_durasi_bulan ?: (int) Setting::resolve(
            KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->value,
            $record->company_id,
            default: (int) KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->default(),
        );
    }

    public function closeReview(): void
    {
        $this->reset('openId', 'reviewDecision', 'reviewNote', 'reviewDurasi', 'revisionNote');
        $this->resetErrorBag();
    }

    protected function canAccess(ContractSubmission $record, User $user): bool
    {
        if ($record->user_id === $user->id) {
            return true;
        }

        if (app(ContractExtensionService::class)->canReview($record, $user)) {
            return true;
        }

        if ($record->decided_by === $user->id || PermissionHelper::hasGlobalRole($user)) {
            return true;
        }

        if ($record->company_id !== $user->company_id) {
            return false;
        }

        if ($user->can('viewAny', ContractSubmission::class)) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->user_id)
            ->exists();
    }

    public function getCanReviewProperty(): bool
    {
        $record = $this->openSubmission;

        return $record
            && $record->status === 'diajukan'
            && app(ContractExtensionService::class)->canReview($record, Auth::user());
    }

    public function getDecisionOptionsProperty(): array
    {
        return collect(ContractDecisionEnum::cases())
            ->mapWithKeys(fn ($d) => [$d->value => $d->getLabel()])
            ->all();
    }

    /** Anchor kontrak baru = tanggal berakhir kontrak berjalan (atau hari ini kalau lewat). */
    public function getAnchorProperty(): Carbon
    {
        $record = $this->openSubmission;
        $end = ($record?->user?->currentContract ?? $record?->userContract)?->contract_end_date;

        return $end ? Carbon::parse($end)->max(Carbon::today()) : Carbon::today();
    }

    /** @return array{0:\Carbon\Carbon,1:\Carbon\Carbon}|null preview periode kontrak baru */
    public function getPreviewProperty(): ?array
    {
        if ($this->reviewDecision !== ContractDecisionEnum::PERPANJANG->value || ! $this->reviewDurasi) {
            return null;
        }

        $start = $this->anchor->copy();

        return [$start, $start->copy()->addMonths(max(1, (int) $this->reviewDurasi))];
    }

    public function submitReview(): void
    {
        $record = $this->openSubmission;
        abort_unless($record && $this->canReview, 403);

        $this->validate([
            'reviewDecision' => 'required|in:' . implode(',', array_keys($this->decisionOptions)),
            'reviewNote' => 'required|string|min:3',
            'reviewDurasi' => 'nullable|integer|min:1|max:24',
        ], [], [
            'reviewDecision' => 'keputusan',
            'reviewNote' => 'catatan keputusan',
            'reviewDurasi' => 'durasi perpanjangan',
        ]);

        if ($this->reviewDecision === ContractDecisionEnum::PERPANJANG->value && ! $this->reviewDurasi) {
            $this->addError('reviewDurasi', 'Durasi perpanjangan wajib diisi.');

            return;
        }

        try {
            app(ContractExtensionService::class)->review(
                $record,
                Auth::id(),
                $this->reviewDecision,
                $this->reviewNote,
                $this->reviewDecision === ContractDecisionEnum::PERPANJANG->value ? (int) $this->reviewDurasi : null,
            );
            Notification::make()->title('Keputusan tersimpan')->success()->send();
            $this->closeReview();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menyimpan')->body($e->getMessage())->danger()->send();
        }
    }

    public function requestRevision(): void
    {
        $record = $this->openSubmission;
        abort_unless($record && $this->canReview, 403);

        $this->validate(
            ['revisionNote' => 'required|string|min:3'],
            [],
            ['revisionNote' => 'catatan revisi'],
        );

        try {
            app(ContractExtensionService::class)->requestRevision($record, Auth::id(), $this->revisionNote);
            Notification::make()->title('Pengajuan dikembalikan ke karyawan untuk revisi dokumen')->success()->send();
            $this->closeReview();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.kontrak-kelola-page');
    }
}
