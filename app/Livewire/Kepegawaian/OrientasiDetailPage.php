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
 *    bulan + keputusan (lanjut_kontrak / tidak_dilanjutkan). Status jadi `dinilai`.
 *  - HR/pengelola orientasi (paralel): tetapkan jadwal presentasi kegiatan (schedulePresentation)
 *    — wajib sebelum tindak lanjut.
 *  - HR: tindak lanjut keputusan — lanjut kontrak (angkat jadi Kontrak) atau tidak dilanjutkan
 *    (catat tanggal efektif berakhir). Status jadi `selesai`.
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

    /** Penetapan jadwal presentasi kegiatan (HR/pengelola orientasi). */
    public ?string $presDate = null;
    public ?string $presTime = null;
    public ?string $presNote = null;

    /** Tindak lanjut HR — keputusan "tidak dilanjutkan": tanggal efektif berakhir hubungan kerja. */
    public ?string $fuEffectiveDate = null;

    /** Tindak lanjut HR — pengangkatan ke kontrak. */
    public ?string $fuContractStart = null;
    public ?string $fuContractEnd = null;

    public function mount(OrientationSubmission $record): void
    {
        $record->load(['user.profile', 'user.employee.orientations', 'employeeContract', 'assessments.scores', 'decidedBy', 'presentationHrBy', 'presentationSupportBy']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
        $this->buildMonths();
        $this->buildRows();

        // Default: hari setelah periode orientasi terakhir berakhir (atau hari ini).
        $last = $record->user?->employee?->orientations->sortByDesc('start_date')->first();
        $anchor = $last?->end_date && $last->end_date->isFuture()
            ? $last->end_date->copy()->addDay()
            : Carbon::today();
        $this->fuEffectiveDate = ($last?->end_date ?? Carbon::today())->toDateString();
        $this->fuContractStart = $anchor->toDateString();
        $this->fuContractEnd = $anchor->copy()->addYear()->subDay()->toDateString();

        // Prefill form jadwal presentasi dari yang sudah ada (final kalau ada, else usulan karyawan).
        $presAt = $record->presentation_confirmed_at ?? $record->presentation_proposed_at;
        if ($presAt) {
            $this->presDate = $presAt->toDateString();
            $this->presTime = $presAt->format('H:i');
        }
        $this->presNote = $record->presentation_note;
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

        if (app(OrientationService::class)->canApprovePresentation($record, $user)) {
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
        $period = $this->record->employeeContract;
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

    /** HR/pengelola orientasi menetapkan (atau merevisi) jadwal presentasi kegiatan = persetujuan HR. */
    public function getCanSchedulePresentationProperty(): bool
    {
        return in_array($this->record->status, ['diajukan', 'dinilai'], true)
            && Auth::user()->can('Update:OrientationSubmission');
    }

    /** Pemegang jabatan pendukung menyetujui jadwal presentasi (persetujuan kedua, urutan bebas). */
    public function getCanApprovePresentationProperty(): bool
    {
        return in_array($this->record->status, ['diajukan', 'dinilai'], true)
            && ! $this->record->presentation_support_at
            && app(OrientationService::class)->canApprovePresentation($this->record, Auth::user());
    }

    /**
     * Tindak lanjut "tidak dilanjutkan": HR catat tanggal efektif berakhir → gate
     * `Update:OrientationSubmission`. Tidak membuat baris apa pun.
     */
    public function getCanFollowUpNotContinuedProperty(): bool
    {
        return $this->record->status === 'dinilai'
            && $this->record->decision === OrientationDecisionEnum::TIDAK_DILANJUTKAN->value
            && Auth::user()->can('Update:OrientationSubmission');
    }

    /**
     * Tindak lanjut kontrak: cuma tandai selesai; kontrak dibuat lewat UserTransition (yang
     * menulis `employee_contracts` setelah approval). Butuh kedua permission rantai itu.
     */
    public function getCanFollowUpContractProperty(): bool
    {
        return $this->record->status === 'dinilai'
            && $this->record->decision === OrientationDecisionEnum::LANJUT_KONTRAK->value
            && Auth::user()->can('Create:UserTransition')
            && Auth::user()->can('Create:EmployeeContract');
    }

    public function getDecisionOptionsProperty(): array
    {
        return collect(OrientationDecisionEnum::cases())
            ->mapWithKeys(fn ($d) => [$d->value => $d->getLabel()])
            ->all();
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

    public function schedulePresentation(): void
    {
        abort_unless($this->canSchedulePresentation, 403);

        $this->validate(
            ['presDate' => 'required|date', 'presTime' => 'required'],
            [],
            ['presDate' => 'tanggal presentasi', 'presTime' => 'jam presentasi'],
        );

        try {
            app(OrientationService::class)->confirmPresentation(
                $this->record,
                "{$this->presDate} {$this->presTime}",
                $this->presNote,
                Auth::id(),
            );
            Notification::make()->title('Persetujuan HR atas jadwal presentasi tersimpan')->success()->send();
            $this->redirectRoute('orientasi.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function approvePresentation(): void
    {
        abort_unless($this->canApprovePresentation, 403);

        try {
            app(OrientationService::class)->approvePresentationBySupport($this->record, Auth::user());
            Notification::make()->title('Persetujuan jadwal presentasi tersimpan')->success()->send();
            $this->redirectRoute('orientasi.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function followUpNotContinued(): void
    {
        abort_unless($this->canFollowUpNotContinued, 403);

        $this->validate(
            ['fuEffectiveDate' => 'required|date'],
            [],
            ['fuEffectiveDate' => 'tanggal efektif berakhir'],
        );

        try {
            app(OrientationService::class)->followUpNotContinued($this->record, $this->fuEffectiveDate, Auth::id());
            Notification::make()->title('Tindak lanjut tersimpan — pengajuan selesai')->success()->send();
            $this->redirectRoute('orientasi.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function followUpContract(): void
    {
        abort_unless($this->canFollowUpContract, 403);

        $this->validate([
            'fuContractStart' => 'required|date',
            'fuContractEnd' => 'required|date|after:fuContractStart',
        ], [], [
            'fuContractStart' => 'tanggal mulai kontrak',
            'fuContractEnd' => 'tanggal akhir kontrak',
        ]);

        try {
            app(OrientationService::class)->followUpContract(
                $this->record,
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
