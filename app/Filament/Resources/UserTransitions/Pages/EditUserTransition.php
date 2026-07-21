<?php

namespace App\Filament\Resources\UserTransitions\Pages;

use App\Enum\UserTransitionStatusEnum;
use App\Filament\Resources\UserTransitions\UserTransitionResource;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\User\UserTransitionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUserTransition extends EditRecord
{
    protected static string $resource = UserTransitionResource::class;

    /** Snapshot approval + sync step — logic ada di UserTransitionService (dipakai bareng
     * portal Kepegawaian). */
    public function mount($record): void
    {
        parent::mount($record);

        app(UserTransitionService::class)->ensureApprovalSnapshot($this->record);

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
                ->action(function ($record) {
                    try {
                        app(UserTransitionService::class)->approve($record, Auth::id());

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
                        app(UserTransitionService::class)->reject($record, Auth::id());

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
}
