<?php

namespace App\Filament\Resources\UserTransitions\Pages;

use App\Enum\Task\TaskStatus;
use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Filament\Resources\UserTransitions\UserTransitionResource;
use App\Models\Master\JobPositions;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUserTransition extends EditRecord
{
    protected static string $resource = UserTransitionResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $approvalService = app(ApprovalFlowService::class);
        $approvalCreationService = app(ApprovalCreationService::class);

        // Jika belum ada approval snapshot, buat baru
        if (!$this->record->approvalSnapshot) {
            try {
                $approvalRepo = app(ApprovalSnapshotRepository::class);
                $masterFlow = $approvalRepo->resolveApprovalFlowForUser(
                    moduleType: 'user_transition',
                    companyId: $this->record->company_id,
                    userId: $this->record->created_by,
                );

                if (!$masterFlow) {
                    Notification::make()
                        ->title('Approval Flow Tidak Ditemukan')
                        ->body('Tidak ada approval flow aktif untuk user transition pada perusahaan ini.')
                        ->danger()
                        ->send();
                } else {
                    $approvalCreationService->createSnapshot(
                        moduleType: 'user_transition',
                        moduleId: $this->record->id,
                        flow: $masterFlow,
                        createdBy: $this->record->created_by,
                        companyId: $this->record->company_id,
                        userId: $this->record->created_by,
                    );

                    Notification::make()
                        ->title('Approval snapshot berhasil dibuat')
                        ->success()
                        ->send();
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Gagal membuat approval snapshot: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        } else {
            // Cek dan sync approval steps jika ada perubahan
            $checkStep = $approvalService->checkStepsSync($this->record->approvalSnapshot);
            if (!$checkStep && $this->record->status !== UserTransitionStatusEnum::APPROVED->value && $this->record->status !== UserTransitionStatusEnum::REJECTED->value) {
                $approvalService->syncStepsFromMaster($this->record->approvalSnapshot);
                $this->record->update([
                    'is_flow_changed' => true,
                ]);

                Notification::make()
                    ->title('Approval flow telah tersinkronisasi dengan data master')
                    ->info()
                    ->send();
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
            //
        ];
    }

    public function getFormActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    try {
                        $user = Auth::user();
    
                        return $record->status === UserTransitionStatusEnum::PENDING->value
                            && app(ApprovalPermissionService::class)
                                ->canApprove($record->approvalSnapshot->id, $user);
                    } catch (\Exception $e) {
                        return false;
                    }
                })
                ->action(function ($record, array $data) {
                    try {
                        app(ApprovalFlowService::class)
                            ->advanceStep($record->approvalSnapshot, Auth::id(), null, 'approved', $data);

                        $record->update([
                            'status'      => UserTransitionStatusEnum::APPROVED->value,
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);

                        $this->applyTransitionChanges($record);

                        $record->refresh();

                        Notification::make()
                            ->title('Perubahan Status Karyawan berhasil disetujui.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menyelesaikan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    try {
                        $user = Auth::user();
    
                        return $record->status === UserTransitionStatusEnum::PENDING->value
                            && app(ApprovalPermissionService::class)
                                ->canApprove($record->approvalSnapshot->id, $user);
                    } catch (\Exception $e) {
                        return false;
                    }
                })
                ->action(function ($record) {
                    try {
                        app(ApprovalFlowService::class)
                            ->advanceStep($record->approvalSnapshot, Auth::id(), null, 'rejected');

                        $record->update([
                            'status' => UserTransitionStatusEnum::REJECTED->value,
                        ]);

                        Notification::make()
                            ->title('Perubahan Status Karyawan berhasil ditolak.')
                            ->danger()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menolak: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    private function applyTransitionChanges($record): void
    {
        $user = $record->user;
        $type = $record->transition_type;

        if (in_array($type, [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value,
        ])) {
            $currentPosition = $user->jobPositions()
                ->whereNull('end_date')
                ->first();

            if ($currentPosition) {
                $currentPosition->update([
                    'end_date' => now(),
                    'status'   => 'inactive',
                ]);
            }

            $user->jobPositions()->create([
                'job_position_id' => $record->transitionable_id,
                'assigned_by'     => auth()->id(),
                'start_date'      => $record->effective_date,
                'status'          => 'active',
            ]);

            $jobPosition = JobPositions::find($record->transitionable_id);

            $user->profile->update([
                'job_position_id' => $jobPosition->id,
                'pangkat_id' => $jobPosition->pangkat_id,
                'golongan_id' => $jobPosition->golongan_id,
                'kelas_id' => $jobPosition->kelas_id,
            ]);

            if ($record->role_id) {
                $user->syncRoles([$record->role]);
            }

            return;
        }

        if ($type === UserTransitionTypeEnum::MUTATION->value) {

            $user->profile?->update([
                'org_structure_id' => $record->transitionable_id,
            ]);

            return;
        }

        if ($type === UserTransitionTypeEnum::TERMINATION->value) {
            $user->update(['is_active' => false]);
            $user->profile->update([
                'job_position_id' => null,
                'pangkat_id' => null,
                'golongan_id' => null,
                'kelas_id' => null,
                'org_structure_id' => null,
                'employee_type_id' => null,
            ]);

            $user->jobPositions()->whereNull('end_date')->update([
                'end_date' => now(),
                'status'   => 'inactive',
            ]);
        }
    }
}
