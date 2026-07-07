<?php

namespace App\Filament\Resources\AttendanceRequests\Pages;

use App\Enum\AttendanceReqStatus;
use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use App\Services\Approval\ApprovalFlowService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAttendanceRequest extends EditRecord
{
    protected static string $resource = AttendanceRequestResource::class;
    protected static ?string $title = 'Edit Pengajuan Absensi';
    public $type;

    public function mount($record): void
    {
        parent::mount($record);

        $this->type = request()->query('type');

        $approvalService = app(ApprovalFlowService::class);
        $checkStep = $approvalService->checkStepsSync($this->record->approvalSnapshot);
        if (!$checkStep && $this->record->status !== AttendanceReqStatus::APPROVED->value && $this->record->status !== AttendanceReqStatus::REJECTED->value) {
            $approvalService->syncStepsFromMaster($this->record->approvalSnapshot);
            $this->record->update([
                'is_flow_changed' => true,
            ]);
        }

        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Refresh relasi yang mungkin berubah
        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
        return $data;
    }

    public function getFormActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => app(\App\Services\Approval\ApprovalPermissionService::class)
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
                ->visible(fn ($record) => app(\App\Services\Approval\ApprovalPermissionService::class)
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
                ->visible(fn ($record) => app(\App\Services\Approval\ApprovalPermissionService::class)
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
