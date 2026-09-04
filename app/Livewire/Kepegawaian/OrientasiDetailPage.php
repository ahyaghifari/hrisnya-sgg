<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\OrientationDecisionEnum;
use App\Helpers\PermissionHelper;
use App\Models\OrientationSubmission;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\OrgHierarchyService;
use App\Services\OrientationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail 1 pengajuan orientasi + timeline. Untuk approver step TERAKHIR: form skor per
 * indikator + keputusan (lulus/perpanjang/tidak_lulus). Approver non-final cuma setuju/tolak.
 * Pola UserTransitionDetailPage.
 */
#[Layout('components.layout', ['title' => 'Detail Pengajuan Orientasi'])]
class OrientasiDetailPage extends Component
{
    public OrientationSubmission $record;

    /** @var array<int, array{score: ?float, comment: ?string}> keyed by index kriteria */
    public array $reviewScores = [];

    public ?string $reviewDecision = null;

    public ?string $reviewNote = null;

    /** Lama perpanjangan (bulan) — hanya dipakai kalau reviewDecision = 'perpanjang'. */
    public int $reviewExtensionMonths = 3;

    public ?string $rejectNote = null;

    public function mount(OrientationSubmission $record): void
    {
        $record->load(['user.profile', 'approvalSnapshot.activity', 'approvalHistories', 'decidedBy']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;

        foreach ($this->criteria as $i => $c) {
            $existing = collect($record->scores ?? [])->firstWhere('criterion', $c['name']);
            $this->reviewScores[$i] = [
                'score' => $existing['score'] ?? null,
                'comment' => $existing['comment'] ?? null,
            ];
        }
    }

    protected function canAccess(OrientationSubmission $record, User $user): bool
    {
        if ($record->user_id === $user->id) {
            return true;
        }

        $snapshot = $record->approvalSnapshot;
        if ($snapshot && app(ApprovalPermissionService::class)->hasPermission($snapshot->id, $user)) {
            return true;
        }

        if ($record->approvalHistories()->where('actor_id', $user->id)->exists()) {
            return true;
        }

        if (PermissionHelper::hasGlobalRole($user)) {
            return true;
        }

        if ($record->company_id !== $user->company_id) {
            return false;
        }

        // HR/pengelola yang punya izin lihat daftar pengajuan orientasi (halaman
        // /kepegawaian/orientasi) - boleh buka detail company sendiri. Form penilaian tetap
        // cuma muncul buat approver sah (canApprove).
        if ($user->can('viewAny', OrientationSubmission::class)) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->user_id)
            ->exists();
    }

    public function getCriteriaProperty(): array
    {
        return app(OrientationService::class)->criteria($this->record->company_id);
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->record);
    }

    public function getCanApproveProperty(): bool
    {
        $snapshot = $this->record->approvalSnapshot;

        if (! $snapshot || ! in_array($this->record->status, ['menunggu_persetujuan', 'dinilai'], true)) {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($snapshot->id, Auth::user());
    }

    public function getIsFinalStepProperty(): bool
    {
        $snapshot = $this->record->approvalSnapshot;

        return $snapshot ? app(ApprovalFlowService::class)->getNextApprover($snapshot) === null : false;
    }

    public function getDecisionOptionsProperty(): array
    {
        return collect(OrientationDecisionEnum::cases())
            ->mapWithKeys(fn ($d) => [$d->value => $d->getLabel()])
            ->all();
    }

    /** Approver step terakhir: simpan skor + keputusan + selesaikan approval. */
    public function submitReview(): void
    {
        abort_unless($this->canApprove && $this->isFinalStep, 403);

        $this->validate([
            'reviewDecision' => 'required|in:' . implode(',', array_keys($this->decisionOptions)),
            'reviewNote' => 'required|string|min:3',
            'reviewScores.*.score' => 'nullable|numeric|min:0',
            'reviewExtensionMonths' => 'required_if:reviewDecision,perpanjang|integer|min:1|max:24',
        ], [], [
            'reviewDecision' => 'keputusan',
            'reviewNote' => 'catatan keputusan',
            'reviewExtensionMonths' => 'lama perpanjangan',
        ]);

        $scores = [];
        foreach ($this->criteria as $i => $c) {
            $scores[] = [
                'criterion' => $c['name'],
                'score' => (float) ($this->reviewScores[$i]['score'] ?? 0),
                'comment' => $this->reviewScores[$i]['comment'] ?? null,
            ];
        }

        try {
            app(OrientationService::class)->review(
                $this->record,
                Auth::id(),
                $scores,
                $this->reviewDecision,
                $this->reviewNote,
                $this->reviewDecision === 'perpanjang' ? (int) $this->reviewExtensionMonths : null,
            );
            $this->record->refresh();
            Notification::make()->title('Penilaian orientasi tersimpan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menyimpan')->body($e->getMessage())->danger()->send();
        }
    }

    /** Approver non-final: teruskan ke approver berikutnya. */
    public function approveStep(): void
    {
        abort_unless($this->canApprove && ! $this->isFinalStep, 403);

        try {
            app(ApprovalFlowService::class)->advanceStep($this->record->approvalSnapshot, Auth::id(), null, 'approved');
            $this->record->update(['status' => 'dinilai']);
            $this->record->refresh();
            Notification::make()->title('Diteruskan ke approver berikutnya')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function rejectStep(): void
    {
        abort_unless($this->canApprove, 403);

        try {
            app(OrientationService::class)->reject($this->record, Auth::id(), $this->rejectNote);
            $this->record->refresh();
            Notification::make()->title('Pengajuan orientasi ditolak')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menolak')->body($e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.orientasi-detail-page');
    }
}
