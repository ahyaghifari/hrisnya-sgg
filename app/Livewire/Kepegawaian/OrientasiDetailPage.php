<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\OrientationDecisionEnum;
use App\Helpers\PermissionHelper;
use App\Models\OrientationSubmission;
use App\Models\User;
use App\Services\OrgHierarchyService;
use App\Services\OrientationService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail 1 pengajuan orientasi. Alur (TANPA Approval Engine, 2026-09-06):
 *  - Penilai (resolve dari sikartap_reviewers context `orientation`): grid skor indikator ×
 *    bulan + keputusan (lanjut_kontrak / perpanjang). Status jadi `dinilai`.
 *  - HR: tindak lanjut keputusan — perpanjang (isi durasi + tgl mulai → periode baru) atau
 *    lanjut kontrak (diarahkan ke Perubahan Status Karyawan). Status jadi `selesai`.
 */
#[Layout('components.layout', ['title' => 'Detail Pengajuan Orientasi'])]
class OrientasiDetailPage extends Component
{
    public OrientationSubmission $record;

    /**
     * Grid penilaian.
     * @var array<int, array{label:string, comment:?string, monthly:array<int, ?string>}>
     */
    public array $rows = [];

    /** Label kolom bulan ("Agu 2026", ...) + tanggal 1-nya (Y-m-d) — sejajar index. */
    public array $monthLabels = [];
    public array $monthDates = [];

    public ?string $reviewDecision = null;
    public ?string $reviewNote = null;

    /** Catatan penilai saat minta karyawan revisi bukti. */
    public ?string $revisionNote = null;

    /** Tindak lanjut HR — perpanjangan orientasi. */
    public ?string $fuStartDate = null;
    public int $fuMonths = 3;

    /** Tindak lanjut HR — pengangkatan ke kontrak. */
    public ?string $fuEmployeeTypeId = null;
    public ?string $fuContractStart = null;
    public ?string $fuContractEnd = null;

    public function mount(OrientationSubmission $record): void
    {
        $record->load(['user.profile', 'user.orientations', 'userOrientation', 'assessments.scores', 'decidedBy']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
        $this->buildMonths();
        $this->buildRows();

        // Default tanggal mulai perpanjangan = hari setelah periode terakhir berakhir (atau hari ini).
        $last = $record->user?->orientations->sortByDesc('start_date')->first();
        $anchor = $last?->end_date && $last->end_date->isFuture()
            ? $last->end_date->copy()->addDay()
            : Carbon::today();
        $this->fuStartDate = $anchor->toDateString();
        $this->fuContractStart = $anchor->toDateString();
        $this->fuContractEnd = $anchor->copy()->addYear()->subDay()->toDateString();
    }

    /** Tipe karyawan kategori Kontrak untuk perusahaan pengajuan (opsi pengangkatan). */
    public function getKontrakEmployeeTypesProperty(): array
    {
        return \App\Models\Master\EmployeeTypes::query()
            ->where('company_id', $this->record->company_id)
            ->where('category', \App\Enum\EmployeeTypeCategory::KONTRAK->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function canAccess(OrientationSubmission $record, User $user): bool
    {
        if ($record->user_id === $user->id) {
            return true;
        }

        if (app(OrientationService::class)->canReview($record, $user)) {
            return true;
        }

        if ($record->decided_by === $user->id) {
            return true;
        }

        if (PermissionHelper::hasGlobalRole($user)) {
            return true;
        }

        if ($record->company_id !== $user->company_id) {
            return false;
        }

        if ($user->can('viewAny', OrientationSubmission::class)) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->user_id)
            ->exists();
    }

    private function buildMonths(): void
    {
        $period = $this->record->userOrientation;
        $start = $period?->start_date ? Carbon::parse($period->start_date)->startOfMonth() : Carbon::now()->startOfMonth();
        $count = max(1, (int) ($period?->duration_months ?? 3));

        for ($i = 0; $i < $count; $i++) {
            $m = $start->copy()->addMonths($i);
            $this->monthDates[] = $m->toDateString();
            $this->monthLabels[] = $m->translatedFormat('M Y');
        }
    }

    private function buildRows(): void
    {
        if ($this->record->assessments->isNotEmpty()) {
            foreach ($this->record->assessments as $a) {
                $byMonth = $a->scores->keyBy(fn ($s) => Carbon::parse($s->period_month)->toDateString());
                $this->rows[] = [
                    'label' => $a->label,
                    'comment' => $a->comment,
                    'monthly' => array_map(
                        fn ($d) => isset($byMonth[$d]) && $byMonth[$d]->score !== null ? (string) $byMonth[$d]->score : null,
                        $this->monthDates
                    ),
                ];
            }

            return;
        }

        foreach (app(OrientationService::class)->criteria($this->record->company_id) as $c) {
            $this->rows[] = [
                'label' => $c['name'],
                'comment' => null,
                'monthly' => array_fill(0, count($this->monthDates), null),
            ];
        }
    }

    public function getCanReviewProperty(): bool
    {
        return $this->record->status === 'diajukan'
            && app(OrientationService::class)->canReview($this->record, Auth::user());
    }

    /**
     * Tindak lanjut perpanjangan: bikin baris `user_orientations` → gate `Create:UserOrientation`.
     */
    public function getCanFollowUpExtendProperty(): bool
    {
        return $this->record->status === 'dinilai'
            && $this->record->decision === OrientationDecisionEnum::PERPANJANG->value
            && Auth::user()->can('Create:UserOrientation');
    }

    /**
     * Tindak lanjut kontrak: cuma tandai selesai; kontrak dibuat lewat UserTransition (yang
     * menulis `user_contracts` setelah approval). Butuh kedua permission rantai itu.
     */
    public function getCanFollowUpContractProperty(): bool
    {
        return $this->record->status === 'dinilai'
            && $this->record->decision === OrientationDecisionEnum::LANJUT_KONTRAK->value
            && Auth::user()->can('Create:UserTransition')
            && Auth::user()->can('Create:UserContract');
    }

    public function getDecisionOptionsProperty(): array
    {
        return collect(OrientationDecisionEnum::cases())
            ->mapWithKeys(fn ($d) => [$d->value => $d->getLabel()])
            ->all();
    }

    /** @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null preview periode perpanjangan */
    public function getExtensionPreviewProperty(): ?array
    {
        if (! $this->fuStartDate || $this->fuMonths < 1) {
            return null;
        }

        $start = Carbon::parse($this->fuStartDate);

        return [$start->copy(), $start->copy()->addMonths($this->fuMonths)->subDay()];
    }

    public function submitReview(): void
    {
        abort_unless($this->canReview, 403);

        abort_if(empty($this->rows), 422, 'Indikator penilaian orientasi belum diatur. Atur dulu di Kepegawaian → Orientasi → tab Indikator.');

        $this->validate([
            'reviewDecision' => 'required|in:' . implode(',', array_keys($this->decisionOptions)),
            'reviewNote' => 'required|string|min:3',
            'rows.*.monthly.*' => 'nullable|integer|in:1,2,3,4,5',
        ], [], [
            'reviewDecision' => 'keputusan',
            'reviewNote' => 'catatan keputusan',
            'rows.*.monthly.*' => 'skor',
        ]);

        $hasScore = collect($this->rows)
            ->flatMap(fn ($r) => $r['monthly'] ?? [])
            ->contains(fn ($v) => $v !== null && $v !== '');

        if (! $hasScore) {
            $this->addError('rows', 'Isi minimal satu skor bulan sebelum menyimpan keputusan.');

            return;
        }

        $payload = [];
        foreach (array_values($this->rows) as $i => $row) {
            $payload[] = [
                'label' => $row['label'],
                'comment' => $row['comment'] ?? null,
                'sort_order' => $i + 1,
                'monthly' => collect($this->monthDates)->map(fn ($d, $k) => [
                    'period_month' => $d,
                    'score' => $row['monthly'][$k] ?? null,
                    'comment' => null,
                ])->all(),
            ];
        }

        try {
            app(OrientationService::class)->review($this->record, Auth::id(), $payload, $this->reviewDecision, $this->reviewNote);
            Notification::make()->title('Keputusan & penilaian tersimpan — menunggu tindak lanjut HR')->success()->send();
            $this->redirectRoute('orientasi.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menyimpan')->body($e->getMessage())->danger()->send();
        }
    }

    /** Penilai kembalikan pengajuan ke karyawan buat perbaiki bukti. */
    public function requestRevision(): void
    {
        abort_unless($this->canReview, 403);

        $this->validate(
            ['revisionNote' => 'required|string|min:3'],
            [],
            ['revisionNote' => 'catatan revisi'],
        );

        try {
            app(OrientationService::class)->requestRevision($this->record, Auth::id(), $this->revisionNote);
            Notification::make()->title('Pengajuan dikembalikan ke karyawan untuk revisi bukti')->success()->send();
            $this->redirectRoute('orientasi.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function followUpExtend(): void
    {
        abort_unless($this->canFollowUpExtend, 403);

        $this->validate([
            'fuStartDate' => 'required|date',
            'fuMonths' => 'required|integer|min:1|max:24',
        ], [], [
            'fuStartDate' => 'tanggal mulai',
            'fuMonths' => 'lama perpanjangan',
        ]);

        try {
            app(OrientationService::class)->followUpExtend($this->record, $this->fuStartDate, (int) $this->fuMonths, Auth::id());
            Notification::make()->title('Periode orientasi baru dibuat')->success()->send();
            $this->redirectRoute('orientasi.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function followUpContract(): void
    {
        abort_unless($this->canFollowUpContract, 403);

        $this->validate([
            'fuEmployeeTypeId' => 'required|string',
            'fuContractStart' => 'required|date',
            'fuContractEnd' => 'required|date|after:fuContractStart',
        ], [], [
            'fuEmployeeTypeId' => 'tipe karyawan',
            'fuContractStart' => 'tanggal mulai kontrak',
            'fuContractEnd' => 'tanggal akhir kontrak',
        ]);

        try {
            app(OrientationService::class)->followUpContract(
                $this->record,
                $this->fuEmployeeTypeId,
                $this->fuContractStart,
                $this->fuContractEnd,
                Auth::id(),
            );
            Notification::make()->title('Kontrak dibuat — status karyawan sekarang Kontrak')->success()->send();
            $this->redirectRoute('orientasi.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.orientasi-detail-page');
    }
}
