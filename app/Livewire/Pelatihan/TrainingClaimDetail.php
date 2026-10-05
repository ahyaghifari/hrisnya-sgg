<?php

namespace App\Livewire\Pelatihan;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\LMS\TrainingClaim;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Detail pengajuan Pelatihan Terlaksana: pemohon & approver di alur persetujuan (modul
 * training_claim) melihat sertifikat + jam per hari. Approver berpermission
 * KELOLA_KLAIM_PELATIHAN (HC/Kepegawaian) boleh mengoreksi JP sebelum approve.
 */
class TrainingClaimDetail extends Component
{
    public TrainingClaim $record;

    public ?string $pendingAction = null;
    public ?string $note = null;
    public ?int $jp = null;

    public bool $editing = false;
    public array $sessionsForm = [];

    public function mount(TrainingClaim $record): void
    {
        $record->load(['approvalSnapshot.activity', 'approvalHistories', 'user', 'sessions']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
        $this->jp = $record->jp;
    }

    protected function canAccess(TrainingClaim $record, User $user): bool
    {
        if ($record->user_id === $user->id || $user->can(CustomPermissionEnum::KELOLA_KLAIM_PELATIHAN->value)) {
            return true;
        }

        $snapshot = $record->approvalSnapshot;

        if ($snapshot && app(ApprovalPermissionService::class)->hasPermission($snapshot->id, $user)) {
            return true;
        }

        if ($record->approvalHistories()->where('actor_id', $user->id)->exists()) {
            return true;
        }

        if ($user->canBrowseAllCompanies()) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->user_id)
            ->exists();
    }

    /** Method biasa (bukan computed) - dipanggil juga dari confirmAction(); computed property
     * di-cache selama 1 request, jadi kotak aksi tetap tampil setelah approve di request yang sama. */
    private function userCanApprove(): bool
    {
        if (! $this->record->approvalSnapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)
            ->canApprove($this->record->approvalSnapshot->id, Auth::user());
    }

    /** Koreksi JP hanya oleh HC/Kepegawaian yang sedang giliran approve. */
    private function userCanEditJp(): bool
    {
        return $this->userCanApprove() && Auth::user()->can(CustomPermissionEnum::KELOLA_KLAIM_PELATIHAN->value);
    }

    /** Ubah jam pelaksanaan: HC/Kepegawaian saat giliran approve, atau kapan saja setelah disetujui. */
    private function userCanEdit(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::KELOLA_KLAIM_PELATIHAN->value)
            && ($this->record->status === 'approved' || ($this->record->status === 'pending' && $this->userCanApprove()));
    }

    public function getCanEditProperty(): bool
    {
        return $this->userCanEdit();
    }

    public function startEdit(): void
    {
        abort_unless($this->userCanEdit(), 403);

        $this->sessionsForm = $this->record->sessions->map(fn ($s) => [
            'date' => $s->date->format('Y-m-d'),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
        ])->all();
        $this->jp = $this->record->jp;
        $this->editing = true;
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->jp = $this->record->jp;
        $this->resetErrorBag();
    }

    public function addSession(): void
    {
        $this->sessionsForm[] = ['date' => null, 'start_time' => null, 'end_time' => null];
    }

    public function removeSession(int $index): void
    {
        if (count($this->sessionsForm) > 1) {
            unset($this->sessionsForm[$index]);
            $this->sessionsForm = array_values($this->sessionsForm);
        }
    }

    /**
     * Simpan perubahan jam pelaksanaan. Klaim pending: JP ikut disegarkan ke saran sistem dari
     * jam baru (approver masih bisa koreksi di kotak Aksi Persetujuan). Klaim approved: JP final
     * diisi manual di form ini, dan Laporan Pelatihan langsung ikut berubah. Dicatat di riwayat.
     */
    public function saveEdit(): void
    {
        abort_unless($this->userCanEdit(), 403);

        $isApproved = $this->record->status === 'approved';

        $this->validate([
            'sessionsForm' => ['required', 'array', 'min:1'],
            'sessionsForm.*.date' => ['required', 'date'],
            'sessionsForm.*.start_time' => ['required', 'date_format:H:i'],
            'sessionsForm.*.end_time' => ['required', 'date_format:H:i', 'after:sessionsForm.*.start_time'],
            ...($isApproved ? ['jp' => ['required', 'integer', 'min:1', 'max:65535']] : []),
        ], [], [
            'sessionsForm.*.date' => 'tanggal',
            'sessionsForm.*.start_time' => 'jam mulai',
            'sessionsForm.*.end_time' => 'jam selesai',
        ]);

        $oldJp = $this->record->jp;
        $newJp = $isApproved ? $this->jp : TrainingClaim::jpFor(TrainingClaim::minutesOf($this->sessionsForm));

        DB::transaction(function () use ($newJp) {
            $this->record->sessions()->delete();
            $this->record->sessions()->createMany($this->sessionsForm);
            $this->record->update(['jp' => $newJp]);

            $snapshot = $this->record->approvalSnapshot;

            ApprovalHistories::create([
                'requestable_type' => ApprovalModuleEnum::TRAINING_CLAIM->value,
                'requestable_id' => $this->record->id,
                'snapshot_id' => $snapshot?->id,
                'activity_id' => $snapshot?->activity?->id,
                'action' => 'updated',
                'actor_id' => Auth::id(),
                'actor_name' => Auth::user()->name,
                'note' => 'Jam pelaksanaan diubah (' . count($this->sessionsForm) . ' hari). JP: ' . ($oldJp ?? '-') . ' → ' . $newJp,
            ]);
        });

        $this->editing = false;
        $this->record->refresh();
        $this->record->load(['approvalSnapshot.activity', 'approvalHistories', 'sessions']);
        $this->jp = $this->record->jp;

        Notification::make()->title('Jam pelaksanaan diperbarui')->success()->send();
    }

    public function getCanApproveProperty(): bool
    {
        return $this->userCanApprove();
    }

    public function getCanEditJpProperty(): bool
    {
        return $this->userCanEditJp();
    }

    public function chooseAction(string $type): void
    {
        $this->pendingAction = $type;
        $this->note = null;
        $this->resetErrorBag();
    }

    public function cancelChoice(): void
    {
        $this->pendingAction = null;
        $this->note = null;
    }

    public function confirmAction(): void
    {
        $this->validate(['note' => ['required', 'string']]);

        $action = ['approve' => 'approved', 'reject' => 'rejected'][$this->pendingAction] ?? null;

        if (! $action) {
            return;
        }

        if ($action === 'approved' && $this->userCanEditJp()) {
            $this->validate(['jp' => ['required', 'integer', 'min:1', 'max:65535']]);
            $this->record->update(['jp' => $this->jp]);
        }

        try {
            app(ApprovalFlowService::class)->advanceStep(
                $this->record->approvalSnapshot,
                Auth::id(),
                $this->note,
                $action
            );

            $this->pendingAction = null;
            $this->note = null;

            $this->record->refresh();
            $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);

            Notification::make()->title('Berhasil diproses')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal memproses: ' . $e->getMessage())->danger()->send();
        }
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->record);
    }

    public function render()
    {
        return view('livewire.pelatihan.training-claim-detail')
            ->layout('components.layout', ['title' => 'Detail Pelatihan Terlaksana']);
    }
}
