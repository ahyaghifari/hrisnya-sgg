<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\JobPositionReqStatus;
use App\Livewire\Kepegawaian\Concerns\HasJabatanForm;
use App\Models\Master\JobPositions;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\JobPosition\JobPositionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Edit Jabatan portal - padanan EditJobPosition admin. Field terkunci total begitu status
 * bukan lagi draft (getIsLockedProperty(), lihat HasJabatanForm) - pengajuan yang sudah
 * diajukan/disetujui/ditolak tidak boleh diam-diam diubah lewat sini, cuma bisa dilihat.
 * Kuota (planned_manpower) juga cuma bisa diisi saat pengajuan baru (lihat
 * jabatan-form-fields.blade.php) - TIDAK bisa diedit lewat sini sama sekali, perubahan kuota
 * cuma lewat tombol "Ajukan Penambahan Kuota" di tabel Jabatan (JabatanPage).
 *
 * Approve/Reject pengajuan (2026-08-10) - sebelumnya cuma ada di admin panel
 * (EditJobPosition::getFormActions()), portal cuma bisa lihat/edit draft (lihat gap di
 * README § Known Gaps). Reuse JobPositionService::approveJobPosition() yang sama persis
 * dipakai admin, jadi efek sampingnya (actual_manpower/capacity org structure) konsisten.
 */
#[Layout('components.layout', ['title' => 'Edit Jabatan'])]
class EditJabatanPage extends Component
{
    use HasJabatanForm;
    use WithFileUploads;

    public bool $showApprovalModal = false;
    public string $approvalAction = '';
    public ?int $actualManpower = null;
    public string $approvalNote = '';

    public function mount(JobPositions $record): void
    {
        abort_unless(
            Auth::user()->canFilterByCompany() || Auth::user()->company_id === $record->company_id,
            403
        );

        $record->loadMissing(['approvalSnapshot.activity', 'approvalHistories', 'user']);

        $canApproveThis = $record->approvalSnapshot
            && app(ApprovalPermissionService::class)->canApprove($record->approvalSnapshot->id, Auth::user());

        // Approver yang bukan pemegang Update:JobPositions (mis. Direktur tanpa akses CRUD
        // Jabatan) tetap boleh buka halaman ini buat approve/reject - form-nya sendiri tetap
        // terkunci total (getIsLockedProperty()) begitu status bukan draft.
        abort_unless(Auth::user()->can('update', $record) || $canApproveThis, 403);

        $this->fillFromRecord($record);
    }

    public function save(): void
    {
        abort_unless(! $this->isLocked, 403);

        $data = $this->validate($this->jabatanRules());
        unset($data['department_id'], $data['division_id'], $data['sub_division_id']);

        try {
            $this->record->update($this->jabatanPayload());

            Notification::make()->title('Jabatan berhasil diperbarui')->success()->send();

            $this->redirect(route('kepegawaian.jabatan'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function getCanApproveProperty(): bool
    {
        if ($this->record->status !== JobPositionReqStatus::REQUESTED->value) {
            return false;
        }

        if (! $this->record->approvalSnapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($this->record->approvalSnapshot->id, Auth::user());
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->record);
    }

    public function openApprovalModal(string $action): void
    {
        abort_unless($this->canApprove, 403);

        $this->approvalAction = $action;
        $this->actualManpower = $action === 'approve' ? $this->record->planned_manpower : null;
        $this->approvalNote = '';
        $this->resetErrorBag();
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal(): void
    {
        $this->showApprovalModal = false;
    }

    public function confirmApproval(): void
    {
        abort_unless($this->canApprove, 403);

        $rules = ['approvalNote' => ['required', 'string']];
        if ($this->approvalAction === 'approve') {
            $rules['actualManpower'] = ['required', 'integer', 'min:0'];
        }

        $this->validate($rules);

        try {
            app(JobPositionService::class)->approveJobPosition(
                jobPositions: $this->record,
                status: $this->approvalAction === 'approve' ? JobPositionReqStatus::APPROVED->value : JobPositionReqStatus::REJECTED->value,
                actualManPower: $this->approvalAction === 'approve' ? (int) $this->actualManpower : 0,
                note: $this->approvalNote,
            );

            $this->record->refresh();
            $this->record->loadMissing(['approvalSnapshot.activity', 'approvalHistories']);
            $this->showApprovalModal = false;

            Notification::make()
                ->title($this->approvalAction === 'approve' ? 'Pengajuan jabatan berhasil disetujui' : 'Pengajuan jabatan ditolak')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal memproses: ' . $e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.edit-jabatan-page');
    }
}
