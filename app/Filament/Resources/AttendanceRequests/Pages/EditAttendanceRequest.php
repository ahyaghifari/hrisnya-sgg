<?php

namespace App\Filament\Resources\AttendanceRequests\Pages;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\AttendanceReqStatus;
use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Attendance\AttendaceReqService;
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

    private function typeLabel($record): string
    {
        return ['overtime' => 'Lembur', 'leave' => 'Cuti', 'permission' => 'Izin'][$record->type] ?? 'Pengajuan';
    }

    /** Batas batal sama dengan portal (AttendaceReqService::cancellationCutoffPassed): boleh sampai hari H. */
    private function cutoffPassed($record): bool
    {
        return app(AttendaceReqService::class)->cancellationCutoffPassed($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Batalkan Cuti/Izin/Lembur atas nama admin yang membatalkan, baik masih diproses maupun
            // sudah approved - logikanya di AttendaceReqService::cancelRequestDirectly() (sama dengan
            // reschedule cuti di portal): kalau sudah approved, roster shift & attendance_records
            // hasil sinkron cuti/izin ikut dicabut; lembur otomatis keluar dari rekap & payroll
            // (keduanya cuma ambil status approved). Batas tanggal sama dengan portal.
            Action::make('cancelRequest')
                ->label(fn ($record) => 'Batalkan ' . $this->typeLabel($record))
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->modalHeading(fn ($record) => 'Batalkan ' . $this->typeLabel($record))
                ->modalDescription(fn ($record) => $record->status === AttendanceReqStatus::APPROVED->value && $record->type !== 'overtime'
                    ? 'Jadwal shift dan data absensi hasil pengajuan ini akan dicabut. Pembatalan tercatat atas nama Anda dan karyawan dikabari.'
                    : 'Pembatalan tercatat atas nama Anda dan karyawan dikabari.')
                ->modalSubmitActionLabel('Ya, Batalkan')
                ->visible(fn ($record) => in_array($record->status, [
                    AttendanceReqStatus::SUBMITTED->value,
                    AttendanceReqStatus::IN_REVIEW->value,
                    AttendanceReqStatus::APPROVED->value,
                ], true) && ! $this->cutoffPassed($record))
                ->schema([
                    Textarea::make('reason')
                        ->label('Alasan Pembatalan')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    // Cek ulang di server - visible() saja tidak cukup kalau halaman terbuka lintas hari.
                    if ($this->cutoffPassed($record)) {
                        Notification::make()->title('Tanggal pengajuan sudah lewat, tidak bisa dibatalkan lagi')->danger()->send();

                        return;
                    }

                    app(AttendaceReqService::class)->cancelRequestDirectly($record, Auth::user(), $data['reason']);

                    Notification::make()->title($this->typeLabel($record) . ' berhasil dibatalkan')->success()->send();

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
