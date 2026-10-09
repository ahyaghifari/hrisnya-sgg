<?php

namespace App\Livewire;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Models\Attendance\AttendanceRequestAttachments;
use App\Models\Attendance\AttendanceRequestCancellations;
use App\Models\Attendance\AttendanceRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\AttendanceCancellationService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class AttendanceRequestDetail extends Component
{
    use WithFileUploads;

    public AttendanceRequests $record;

    /** Susulan lampiran Cuti Melahirkan - 1 kali saja (lihat getCanUploadAttachmentProperty()). */
    public array $lampiran = [];
    public bool $confirmingUpload = false;

    public ?string $pendingAction = null;
    public ?string $note = null;

    /** Revisi tanggal oleh approver saat approve — cuma untuk Cuti Khusus & Izin Khusus. */
    public ?string $reviseStart = null;
    public ?string $reviseEnd = null;

    /** Aksi self-service pemilik pengajuan (bukan approver): 'self_cancel' (batal langsung, masih
     *  in_review), 'request_cancel' (ajukan pembatalan, sudah approved), atau 'reschedule'
     *  (khusus Cuti - lihat getCanRescheduleProperty()). */
    public ?string $selfAction = null;
    public ?string $selfActionReason = null;

    /** Tanggal baru untuk aksi 'reschedule' - lihat getCanRescheduleProperty(). */
    public ?string $rescheduleStart = null;
    public ?string $rescheduleEnd = null;

    /** Approve/Reject untuk pengajuan pembatalan (aktivitas terpisah dari $pendingAction di atas
     *  yang khusus pengajuan Cuti/Izin aslinya). */
    public ?string $cancellationPendingAction = null;
    public ?string $cancellationNote = null;

    public function mount(AttendanceRequests $record): void
    {
        $record->load([
            'approvalSnapshot.activity', 'approvalHistories', 'attachments',
            'cancellationRequests' => fn ($q) => $q->latest()->limit(1),
        ]);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
    }

    protected function canAccess(AttendanceRequests $record, User $user): bool
    {
        if ($record->user_id === $user->id) {
            return true;
        }

        $snapshot = $record->approvalSnapshot;

        if ($snapshot && app(ApprovalPermissionService::class)->hasPermission($snapshot->id, $user)) {
            return true;
        }

        $cancellationSnapshot = $this->resolveActiveCancellation($record)?->approvalSnapshot;

        if ($cancellationSnapshot && app(ApprovalPermissionService::class)->hasPermission($cancellationSnapshot->id, $user)) {
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

    /** Pemilik boleh susul lampiran Cuti Melahirkan (lampiran opsional saat ajukan) selama masih
     *  kosong. Setelah terisi otomatis tertutup = kesempatan 1 kali, tanpa kolom tambahan. */
    public function getCanUploadAttachmentProperty(): bool
    {
        return $this->record->user_id === Auth::id()
            && $this->record->type === 'leave'
            && $this->record->leaveDetails()->first()?->category === LeaveTypeCategoryEnum::MELAHIRKAN->value
            && in_array($this->record->status, ['in_review', 'approved'], true)
            && ! $this->record->attachments()->exists();
    }

    public function askUpload(): void
    {
        abort_unless($this->canUploadAttachment, 403);

        $this->validate([
            'lampiran' => ['required', 'array', 'min:1'],
            'lampiran.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], ['lampiran.required' => 'Pilih file lampiran dulu.', 'lampiran.min' => 'Pilih file lampiran dulu.'], ['lampiran.*' => 'Lampiran']);

        $this->confirmingUpload = true;
    }

    public function confirmUpload(): void
    {
        abort_unless($this->canUploadAttachment, 403);

        foreach ($this->lampiran as $file) {
            AttendanceRequestAttachments::create([
                'request_id' => $this->record->id,
                'file_path' => \Illuminate\Support\Facades\Storage::disk('public')->putFile("leave/{$this->record->id}", $file),
            ]);
        }

        $this->lampiran = [];
        $this->confirmingUpload = false;
        $this->record->load('attachments');
        unset($this->canUploadAttachment);

        Notification::make()->title('Lampiran berhasil diunggah')->success()->send();
    }

    public function getCanApproveProperty(): bool
    {
        if (! $this->record->approvalSnapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)
            ->canApprove($this->record->approvalSnapshot->id, Auth::user());
    }

    /** Pengajuan pembatalan terbaru (kalau ada) untuk record ini - null kalau belum pernah
     *  diajukan. Dipakai baik saat mount() (canAccess) maupun render (status/aksi). */
    private function resolveActiveCancellation(?AttendanceRequests $record = null): ?AttendanceRequestCancellations
    {
        $record ??= $this->record;

        return $record->relationLoaded('cancellationRequests')
            ? $record->cancellationRequests->first()
            : $record->cancellationRequests()->latest()->first();
    }

    public function getActiveCancellationProperty(): ?AttendanceRequestCancellations
    {
        return $this->resolveActiveCancellation();
    }

    /** Pemilik pengajuan boleh batal LANGSUNG - masih in_review, tipe Cuti/Izin/Lembur, belum
     *  lewat tanggal mulai. */
    public function getCanSelfCancelProperty(): bool
    {
        return $this->record->user_id === Auth::id()
            && in_array($this->record->type, ['leave', 'permission', 'overtime'], true)
            && $this->record->approvalSnapshot?->status === 'in_review'
            && ! app(AttendaceReqService::class)->cancellationCutoffPassed($this->record);
    }

    /** Pemilik pengajuan boleh AJUKAN pembatalan - sudah approved, belum lewat tanggal mulai,
     *  belum ada pengajuan pembatalan yang masih diproses. */
    public function getCanRequestCancellationProperty(): bool
    {
        return $this->record->user_id === Auth::id()
            && in_array($this->record->type, ['leave', 'permission', 'overtime'], true)
            && $this->record->status === 'approved'
            && ! app(AttendaceReqService::class)->cancellationCutoffPassed($this->record)
            && $this->activeCancellation?->status !== 'in_review';
    }

    /** Pemilik pengajuan boleh RESCHEDULE - KHUSUS Cuti (bukan Izin), masih in_review ATAU sudah
     *  approved, belum lewat tanggal mulai, belum ada pengajuan pembatalan yang masih diproses.
     *  Beda dari canRequestCancellation - ini otomatis batal LANGSUNG (tanpa approval) lalu
     *  buka pengajuan baru, lihat AttendaceReqService::rescheduleLeaveRequest(). */
    public function getCanRescheduleProperty(): bool
    {
        return $this->record->user_id === Auth::id()
            && $this->record->type === 'leave'
            && in_array($this->record->status, ['in_review', 'approved'], true)
            && ! app(AttendaceReqService::class)->cancellationCutoffPassed($this->record)
            && $this->activeCancellation?->status !== 'in_review';
    }

    public function getCanApproveCancellationProperty(): bool
    {
        $snapshot = $this->activeCancellation?->approvalSnapshot;

        if (! $snapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($snapshot->id, Auth::user());
    }

    public function getCancellationTimelineProperty(): array
    {
        return $this->activeCancellation
            ? app(ApprovalTimelineService::class)->build($this->activeCancellation)
            : [];
    }

    public function chooseAction(string $type): void
    {
        $this->pendingAction = $type;
        $this->note = null;
        $this->resetErrorBag();

        [$this->reviseStart, $this->reviseEnd] = $type === 'approve' && $this->dateRevisable
            ? $this->currentRequestDates()
            : [null, null];
    }

    public function cancelChoice(): void
    {
        $this->pendingAction = null;
        $this->note = null;
        $this->reviseStart = null;
        $this->reviseEnd = null;
    }

    /** $type: 'self_cancel' (batal langsung, masih in_review) atau 'request_cancel' (ajukan
     *  pembatalan, sudah approved) - lihat getCanSelfCancelProperty()/getCanRequestCancellationProperty(). */
    public function chooseSelfAction(string $type): void
    {
        $this->selfAction = $type;
        $this->selfActionReason = null;

        if ($type === 'reschedule') {
            [$this->rescheduleStart, $this->rescheduleEnd] = $this->currentRequestDates();
        }

        $this->resetErrorBag();
    }

    public function cancelSelfAction(): void
    {
        $this->selfAction = null;
        $this->selfActionReason = null;
        $this->rescheduleStart = null;
        $this->rescheduleEnd = null;
    }

    public function confirmSelfAction(): void
    {
        $this->validate(['selfActionReason' => ['required', 'string']], [], ['selfActionReason' => 'Alasan']);

        if ($this->selfAction === 'reschedule') {
            $this->validate([
                'rescheduleStart' => ['required', 'date'],
                'rescheduleEnd' => ['required', 'date', 'after_or_equal:rescheduleStart'],
            ], [], ['rescheduleStart' => 'Tanggal mulai baru', 'rescheduleEnd' => 'Tanggal selesai baru']);
        }

        // Guard server-side - tombolnya sudah disembunyikan di blade kalau tidak eligible, tapi
        // chooseSelfAction()/confirmSelfAction() bisa dipanggil langsung lewat Livewire wire:click
        // manapun, jadi statusnya dicek ulang di sini (bukan cuma andalkan UI).
        abort_unless(
            ($this->selfAction === 'self_cancel' && $this->canSelfCancel)
                || ($this->selfAction === 'request_cancel' && $this->canRequestCancellation)
                || ($this->selfAction === 'reschedule' && $this->canReschedule),
            403
        );

        try {
            if ($this->selfAction === 'self_cancel') {
                app(ApprovalFlowService::class)->advanceStep(
                    $this->record->approvalSnapshot,
                    Auth::id(),
                    $this->selfActionReason,
                    'cancelled'
                );

                Notification::make()->title('Pengajuan berhasil dibatalkan')->success()->send();
            } elseif ($this->selfAction === 'request_cancel') {
                app(AttendanceCancellationService::class)->requestCancellation(
                    $this->record,
                    Auth::user(),
                    $this->selfActionReason
                );

                Notification::make()->title('Pengajuan pembatalan berhasil dikirim, menunggu persetujuan')->success()->send();
            } elseif ($this->selfAction === 'reschedule') {
                app(AttendaceReqService::class)->rescheduleLeaveRequest(
                    $this->record,
                    $this->rescheduleStart,
                    $this->rescheduleEnd,
                    Auth::user(),
                    $this->selfActionReason
                );

                Notification::make()->title('Cuti berhasil dijadwalkan ulang, menunggu persetujuan baru')->success()->send();

                // Pengajuan lama (record ini) sudah cancelled, halaman detailnya sudah tidak
                // relevan lagi - balik ke daftar, sama pola dengan SubmitLeaveRequest::submit().
                $this->redirect(route('my-attendance-requests'), navigate: true);

                return;
            }

            $this->selfAction = null;
            $this->selfActionReason = null;
            $this->rescheduleStart = null;
            $this->rescheduleEnd = null;

            $this->record->refresh();
            $this->record->load([
                'approvalSnapshot.activity', 'approvalHistories', 'attachments',
                'cancellationRequests' => fn ($q) => $q->latest()->limit(1),
            ]);

            unset($this->canSelfCancel, $this->canRequestCancellation, $this->canReschedule, $this->activeCancellation, $this->cancellationTimeline);
        } catch (\Exception $e) {
            Notification::make()->title('Gagal: ' . $e->getMessage())->danger()->send();
        }
    }

    public function chooseCancellationAction(string $type): void
    {
        $this->cancellationPendingAction = $type;
        $this->cancellationNote = null;
        $this->resetErrorBag();
    }

    public function cancelCancellationChoice(): void
    {
        $this->cancellationPendingAction = null;
        $this->cancellationNote = null;
    }

    /** Approve/Reject oleh approver ALUR PEMBATALAN (bisa beda orang dari approver pengajuan
     *  aslinya) - terpisah dari confirmAction() yang menangani pengajuan Cuti/Izin asli. */
    public function confirmCancellationAction(): void
    {
        $this->validate(['cancellationNote' => ['required', 'string']], [], ['cancellationNote' => 'Catatan']);

        $actionMap = ['approve' => 'approved', 'reject' => 'rejected'];
        $action = $actionMap[$this->cancellationPendingAction] ?? null;

        if (! $action) {
            return;
        }

        abort_unless($this->canApproveCancellation, 403);

        try {
            app(ApprovalFlowService::class)->advanceStep(
                $this->activeCancellation->approvalSnapshot,
                Auth::id(),
                $this->cancellationNote,
                $action
            );

            $this->cancellationPendingAction = null;
            $this->cancellationNote = null;

            $this->record->refresh();
            $this->record->load([
                'approvalSnapshot.activity', 'approvalHistories', 'attachments',
                'cancellationRequests' => fn ($q) => $q->latest()->limit(1),
            ]);

            unset($this->canApproveCancellation, $this->activeCancellation, $this->cancellationTimeline);

            Notification::make()->title('Berhasil diproses')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal memproses: ' . $e->getMessage())->danger()->send();
        }
    }

    /**
     * Tanggal pengajuan boleh direvisi approver saat approve — HANYA Cuti Khusus
     * (LeaveTypeCategoryEnum::isCutiKhusus) & Izin Khusus (PermissionTypeCategoryEnum::IZIN_KHUSUS).
     */
    public function getDateRevisableProperty(): bool
    {
        if (! $this->canApprove) {
            return false;
        }

        if ($this->record->type === 'leave' && $this->record->leaveDetails->isNotEmpty()) {
            $cat = LeaveTypeCategoryEnum::tryFrom($this->record->leaveDetails->first()->category ?? '');

            return (bool) $cat?->isCutiKhusus();
        }

        if ($this->record->type === 'permission' && $this->record->permissionDetails->isNotEmpty()) {
            return $this->record->permissionDetails->first()->category === PermissionTypeCategoryEnum::IZIN_KHUSUS->value;
        }

        return false;
    }

    /** @return array{0: ?string, 1: ?string} [start, end] tanggal pengajuan saat ini (YYYY-MM-DD). */
    private function currentRequestDates(): array
    {
        $norm = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->toDateString() : null;

        if ($this->record->type === 'leave') {
            $d = $this->record->leaveDetails->first();

            return [$norm($d?->start_date), $norm($d?->end_date ?? $d?->start_date)];
        }

        $d = $this->record->permissionDetails->first();

        return [$norm($d?->date), $norm($d?->end_date ?? $d?->date)];
    }

    public function confirmAction(): void
    {
        $revising = $this->pendingAction === 'approve' && $this->dateRevisable;

        $this->validate([
            'note' => ['required', 'string'],
            ...($revising ? [
                'reviseStart' => ['required', 'date'],
                'reviseEnd' => ['required', 'date', 'after_or_equal:reviseStart'],
            ] : []),
        ]);

        $actionMap = ['approve' => 'approved', 'reject' => 'rejected', 'cancel' => 'cancelled'];
        $action = $actionMap[$this->pendingAction] ?? null;

        if (! $action) {
            return;
        }

        try {
            if ($revising) {
                app(AttendaceReqService::class)->reviseRequestDates($this->record, $this->reviseStart, $this->reviseEnd);
            }

            app(ApprovalFlowService::class)->advanceStep(
                $this->record->approvalSnapshot,
                Auth::id(),
                $this->note,
                $action
            );

            $this->pendingAction = null;
            $this->note = null;
            $this->reviseStart = null;
            $this->reviseEnd = null;

            $this->record->refresh();
            $this->record->load([
                'approvalSnapshot.activity', 'approvalHistories', 'attachments',
            ]);

            // Computed (canApprove/dateRevisable/dst) sudah sempat diakses & di-memoize di atas
            // sebelum status pengajuan berubah — buang cache-nya supaya render ulang memakai
            // state terbaru (kartu "Aksi Persetujuan" ikut hilang setelah disetujui).
            unset($this->canApprove, $this->dateRevisable, $this->quotaInfo, $this->timeline, $this->tenureWarning);

            Notification::make()
                ->title('Berhasil diproses')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal memproses: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->record);
    }

    /**
     * Info kuota pemohon (Cuti Tahunan/Izin) buat ditampilkan di halaman approval — approver
     * bisa lihat sisa jatah pemohon SAAT itu juga, bukan widget terpisah. Lihat
     * planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §8. Cuma relevan
     * untuk Cuti & Izin — Lembur tidak punya konsep kuota.
     */
    public function getQuotaInfoProperty(): ?array
    {
        $service = app(AttendaceReqService::class);

        if ($this->record->type === 'leave' && $this->record->leaveDetails->isNotEmpty()) {
            $leave = $this->record->leaveDetails->first();

            return [
                'kind' => 'leave',
                'balance' => $service->getLeaveUserBalance($this->record->user_id, $leave->category ?? '', $leave->start_date),
                'periode' => $service->getCutiTahunanPeriodeUsage($this->record->user, $leave->category ?? '', $leave->start_date),
            ];
        }

        if ($this->record->type === 'permission' && $this->record->permissionDetails->isNotEmpty()) {
            $permission = $this->record->permissionDetails->first();
            $category = $permission->category ? \App\Enum\Attendance\PermissionTypeCategoryEnum::tryFrom($permission->category) : null;
            $group = $category ? \App\Enum\Attendance\PermissionGroupEnum::fromCategory($category) : null;

            // Tanpa category (jenis izin custom lama tanpa kategori) tidak ada kelompok yang
            // bisa ditentukan - getIzinPeriodeUsage() sekarang wajib PermissionGroupEnum, jadi
            // info kuota memang tidak relevan/tidak bisa dihitung untuk kasus ini.
            if (! $group) {
                return null;
            }

            $usage = $service->getIzinPeriodeUsage($this->record->user, $group, $permission->date);

            if (! $usage) {
                return null;
            }

            return [
                'kind' => 'permission',
                'usage' => $usage,
            ];
        }

        return null;
    }

    /** Warning masa kerja minimal (AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN) — cuma
     * relevan buat pengajuan Cuti, ditampilkan ke approver, tidak memblokir apa pun. */
    public function getTenureWarningProperty(): ?string
    {
        if ($this->record->type !== 'leave') {
            return null;
        }

        return app(AttendaceReqService::class)->getMinimumTenureWarning($this->record->user);
    }

    public function render()
    {
        return view('livewire.attendance-request-detail')
            ->layout('components.layout', ['title' => 'Detail Pengajuan']);
    }
}
