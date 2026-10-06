<?php

namespace App\Filament\Resources\AttendanceRequests\Pages;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\AttendanceReqStatus;
use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Services\Approval\ApprovalFlowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EditAttendanceRequest extends EditRecord
{
    protected static string $resource = AttendanceRequestResource::class;
    protected static ?string $title = 'Edit Pengajuan Absensi';
    public $type;

    public function mount($record): void
    {
        parent::mount($record);

        $this->type = request()->query('type');

        // Pengajuan yang dibuat admin langsung tanpa approval (lihat AttendaceReqService::
        // storePermissionRequest $skipApproval) tidak punya approvalSnapshot sama sekali -
        // tidak ada flow buat di-sync.
        if ($this->record->approvalSnapshot) {
            $approvalService = app(ApprovalFlowService::class);
            $checkStep = $approvalService->checkStepsSync($this->record->approvalSnapshot);
            if (!$checkStep && $this->record->status !== AttendanceReqStatus::APPROVED->value && $this->record->status !== AttendanceReqStatus::REJECTED->value) {
                $approvalService->syncStepsFromMaster($this->record->approvalSnapshot);
                $this->record->update([
                    'is_flow_changed' => true,
                ]);
            }
        }

        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Refresh relasi yang mungkin berubah
        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Batalkan lembur yang SUDAH approved atas nama admin yang membatalkan (cancelled_by +
            // riwayat approval). Status cancelled otomatis keluar dari payroll & rekap lembur
            // (keduanya cuma ambil status approved). Lembur yang masih in_review dibatalkan lewat
            // tombol "Batalkan" approver di bawah.
            Action::make('cancelOvertime')
                ->label('Batalkan Lembur')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->modalHeading('Batalkan Lembur')
                ->modalDescription('Lembur yang dibatalkan tidak lagi dihitung di rekap lembur dan payroll. Pembatalan tercatat atas nama Anda.')
                ->modalSubmitActionLabel('Ya, Batalkan')
                ->visible(fn ($record) => $record->type === 'overtime' && in_array($record->status, [
                    AttendanceReqStatus::SUBMITTED->value,
                    AttendanceReqStatus::IN_REVIEW->value,
                    AttendanceReqStatus::APPROVED->value,
                ]))
                ->schema([
                    Textarea::make('reason')
                        ->label('Alasan Pembatalan')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    DB::transaction(function () use ($record, $data) {
                        $record->update([
                            'status' => AttendanceReqStatus::CANCELLED->value,
                            'cancelled_by' => Auth::id(),
                            'cancelled_at' => now(),
                        ]);

                        if ($snapshot = $record->approvalSnapshot) {
                            $snapshot->update(['status' => 'cancelled']);
                            $snapshot->activity?->update([
                                'status' => 'cancelled',
                                'acted_by' => Auth::id(),
                                'acted_at' => now(),
                                'note' => $data['reason'],
                            ]);

                            ApprovalHistories::create([
                                'requestable_type' => $snapshot->module_type,
                                'requestable_id' => $snapshot->module_id,
                                'snapshot_id' => $snapshot->id,
                                'activity_id' => $snapshot->activity?->id,
                                'action' => 'cancelled',
                                'actor_id' => Auth::id(),
                                'actor_name' => Auth::user()->name,
                                'note' => $data['reason'],
                            ]);
                        }
                    });

                    Notification::make()->title('Lembur berhasil dibatalkan')->success()->send();

                    $this->redirect(AttendanceRequestResource::getUrl('edit', ['record' => $record, 'type' => $this->type]));
                }),

            // Cuma boleh hapus pengajuan yang dibuat admin lewat /admin (created_by beda dari
            // user_id pemilik izin/cuti/lembur) - pengajuan mandiri karyawan lewat portal
            // (created_by === user_id) tetap bisa diedit tapi tidak bisa dihapus dari sini.
            Action::make('deleteRequest')
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hapus Pengajuan')
                ->modalDescription('Ini akan menghapus PERMANEN pengajuan ini beserta detail (cuti/izin/lembur), snapshot approval, dan riwayatnya. Tidak bisa dibatalkan.')
                ->modalSubmitActionLabel('Ya, Hapus')
                ->visible(fn ($record) => $record->created_by && $record->created_by !== $record->user_id)
                ->action(function ($record) {
                    DB::transaction(function () use ($record) {
                        ApprovalSnapshots::where('module_type', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
                            ->where('module_id', $record->id)
                            ->each(fn ($snapshot) => $snapshot->delete());

                        $record->delete();
                    });

                    Notification::make()
                        ->title('Pengajuan berhasil dihapus')
                        ->success()
                        ->send();

                    $this->redirect(AttendanceRequestResource::getUrl('index', ['type' => $this->type]));
                }),
        ];
    }

    public function getFormActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->approvalSnapshot && app(\App\Services\Approval\ApprovalPermissionService::class)
                    ->canApprove($record->approvalSnapshot->id, Auth::user())
                )
                ->schema([
                    Textarea::make('approval_note')
                        ->label('Catatan Persetujuan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan catatan untuk persetujuan ini...')
                        ->columnSpan('full'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $note = $data['approval_note'] ?? null;
                        
                        app(ApprovalFlowService::class)
                            ->advanceStep($record->approvalSnapshot, Auth::id(), $note, 'approved');
                            
                        $record->refresh();
                        $record->approvalSnapshot->refresh();
                        $record->approvalSnapshot->activity->refresh();
                        
                        $record->load(['approvalSnapshot.activity']);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil disetujui')
                            ->success()
                            ->send();
                            
                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                        
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal menyetujui: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->approvalSnapshot && app(\App\Services\Approval\ApprovalPermissionService::class)
                    ->canApprove($record->approvalSnapshot->id, Auth::user())
                )
                ->schema([
                    Textarea::make('approval_note')
                        ->label('Catatan Penolakan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan catatan untuk penolakan ini...')
                        ->columnSpan('full'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $note = $data['approval_note'] ?? null;
                        
                        app(ApprovalFlowService::class)
                            ->advanceStep($record->approvalSnapshot, Auth::id(), $note, 'rejected');
                            
                        $record->refresh();
                        $record->approvalSnapshot->refresh();
                        $record->approvalSnapshot->activity->refresh();
                        
                        $record->load(['approvalSnapshot.activity']);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil ditolak')
                            ->success()
                            ->send();
                            
                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                        
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal menolak: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Batalkan')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->approvalSnapshot && app(\App\Services\Approval\ApprovalPermissionService::class)
                    ->canApprove($record->approvalSnapshot->id, Auth::user())
                )
                ->schema([
                    Textarea::make('approval_note')
                        ->label('Alasan Pembatalan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan alasan pembatalan pengajuan ini...')
                        ->columnSpan('full'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $note = $data['approval_note'] ?? null;

                        app(ApprovalFlowService::class)
                            ->advanceStep($record->approvalSnapshot, Auth::id(), $note, 'cancelled');

                        $record->refresh();
                        $record->approvalSnapshot->refresh();
                        $record->approvalSnapshot->activity->refresh();

                        $record->load(['approvalSnapshot.activity']);

                        \Filament\Notifications\Notification::make()
                            ->title('Pengajuan berhasil dibatalkan')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));

                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal membatalkan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
