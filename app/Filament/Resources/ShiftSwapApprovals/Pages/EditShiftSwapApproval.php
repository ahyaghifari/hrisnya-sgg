<?php

namespace App\Filament\Resources\ShiftSwapApprovals\Pages;

use App\Filament\Resources\ShiftSwapApprovals\ShiftSwapApprovalResource;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditShiftSwapApproval extends EditRecord
{
    protected static string $resource = ShiftSwapApprovalResource::class;

    public function getTitle(): string
    {
        return 'Detail Pengajuan Tukar Shift';
    }

    public function mount($record): void
    {
        parent::mount($record);

        // Auto-sync approval steps if master flow changed
        $snapshot = $this->record->approvalSnapshot;
        if ($snapshot &&
            !in_array($this->record->status, ['approved', 'rejected', 'cancelled'])
        ) {
            $approvalService = app(ApprovalFlowService::class);
            if (!$approvalService->checkStepsSync($snapshot)) {
                $approvalService->syncStepsFromMaster($snapshot);
            }
        }

        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
        return $data;
    }

    /** No editable form fields â€” view-only. */
    public function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Setujui Tukar Shift')
                ->modalDescription('Apakah Anda yakin ingin menyetujui pengajuan tukar shift ini?')
                ->modalSubmitActionLabel('Ya, Setujui')
                ->schema([
                    Textarea::make('approval_note')
                        ->label('Catatan Persetujuan')
                        ->rows(3)
                        ->placeholder('Tambahkan catatan (opsional)...')
                        ->columnSpanFull(),
                ])
                ->visible(function ($record) {
                    $snapshot = $record->approvalSnapshot;
                    return $snapshot
                        && in_array($record->status, ['pending', 'in_review'])
                        && app(ApprovalPermissionService::class)->canApprove($snapshot->id, Auth::user());
                })
                ->action(function ($record, array $data) {
                    try {
                        app(ApprovalFlowService::class)->advanceStep(
                            $record->approvalSnapshot,
                            Auth::id(),
                            $data['approval_note'] ?? null,
                            'approved'
                        );

                        $record->refresh();

                        Notification::make()
                            ->title('Pengajuan tukar shift berhasil disetujui.')
                            ->success()
                            ->send();

                        $this->redirect(ShiftSwapApprovalResource::getUrl('edit', ['record' => $record]));

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal menyetujui: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label('Tolak')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Tolak Pengajuan Tukar Shift')
                ->modalSubmitActionLabel('Ya, Tolak')
                ->schema([
                    Textarea::make('rejection_note')
                        ->label('Alasan Penolakan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan alasan penolakan...')
                        ->columnSpanFull(),
                ])
                ->visible(function ($record) {
                    $snapshot = $record->approvalSnapshot;
                    return $snapshot
                        && in_array($record->status, ['pending', 'in_review'])
                        && app(ApprovalPermissionService::class)->canApprove($snapshot->id, Auth::user());
                })
                ->action(function ($record, array $data) {
                    try {
                        app(ApprovalFlowService::class)->advanceStep(
                            $record->approvalSnapshot,
                            Auth::id(),
                            $data['rejection_note'] ?? null,
                            'rejected'
                        );

                        $record->refresh();

                        Notification::make()
                            ->title('Pengajuan tukar shift berhasil ditolak.')
                            ->danger()
                            ->send();

                        $this->redirect(ShiftSwapApprovalResource::getUrl('edit', ['record' => $record]));

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal menolak: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ShiftSwapApprovalResource::getUrl('index')),
        ];
    }
}
