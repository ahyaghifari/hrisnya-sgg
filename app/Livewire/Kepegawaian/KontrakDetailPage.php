<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\ContractDecisionEnum;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Helpers\PermissionHelper;
use App\Models\ContractSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContractExtensionService;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail 1 pengajuan perpanjangan kontrak. Penilai (resolve `sikartap_reviewers` context
 * `contract`): keputusan `perpanjang` (+ durasi bulan) atau `tidak_perpanjang` + catatan.
 * `perpanjang` langsung bikin baris user_contracts baru. Bisa juga minta revisi dokumen.
 */
#[Layout('components.layout', ['title' => 'Detail Pengajuan Perpanjangan Kontrak'])]
class KontrakDetailPage extends Component
{
    public ContractSubmission $record;

    public ?string $reviewDecision = null;
    public ?string $reviewNote = null;
    public ?int $reviewDurasi = null;
    public ?string $revisionNote = null;

    public function mount(ContractSubmission $record): void
    {
        $record->load(['user.profile', 'user.currentContract', 'userContract', 'newContract', 'documents', 'decidedBy']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
        $this->reviewDurasi = $record->usulan_durasi_bulan ?: (int) Setting::resolve(
            KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->value,
            $record->company_id,
            default: (int) KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->default(),
        );
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
        return $this->record->status === 'diajukan'
            && app(ContractExtensionService::class)->canReview($this->record, Auth::user());
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
        $end = ($this->record->user?->currentContract ?? $this->record->userContract)?->contract_end_date;

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
        abort_unless($this->canReview, 403);

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
                $this->record,
                Auth::id(),
                $this->reviewDecision,
                $this->reviewNote,
                $this->reviewDecision === ContractDecisionEnum::PERPANJANG->value ? (int) $this->reviewDurasi : null,
            );
            Notification::make()->title('Keputusan tersimpan')->success()->send();
            $this->redirectRoute('kontrak.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menyimpan')->body($e->getMessage())->danger()->send();
        }
    }

    public function requestRevision(): void
    {
        abort_unless($this->canReview, 403);

        $this->validate(
            ['revisionNote' => 'required|string|min:3'],
            [],
            ['revisionNote' => 'catatan revisi'],
        );

        try {
            app(ContractExtensionService::class)->requestRevision($this->record, Auth::id(), $this->revisionNote);
            Notification::make()->title('Pengajuan dikembalikan ke karyawan untuk revisi dokumen')->success()->send();
            $this->redirectRoute('kontrak.show', ['record' => $this->record->id], navigate: true);
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.kontrak-detail-page');
    }
}
