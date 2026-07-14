<?php

namespace App\Filament\Resources\UserShiftSwaps\Pages;

use App\Filament\Resources\UserShiftSwaps\UserShiftSwapResource;
use App\Repositories\Approval\ApprovalHistoryRepository;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EditUserShiftSwap extends EditRecord
{
    protected static string $resource = UserShiftSwapResource::class;

    public function getTitle(): string
    {
        return 'Detail Tukar Shift';
    }

    /**
     * No save/delete form actions — view-only, cancel is in header.
     */
    public function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel_swap')
                ->label('Batalkan Pengajuan')
                ->color('danger')
                ->icon('heroicon-o-x-mark')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Pengajuan Tukar Shift')
                ->modalDescription('Apakah Anda yakin ingin membatalkan pengajuan ini? Tindakan ini tidak dapat dibatalkan.')
                ->modalSubmitActionLabel('Ya, Batalkan')
                ->visible(fn ($record) =>
                    in_array($record->status, ['pending', 'in_review']) &&
                    $record->requester_id === Auth::id()
                )
                ->action(function ($record) {
                    try {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'cancelled']);

                            app(ApprovalHistoryRepository::class)->create([
                                'requestable_type' => 'swap_shift_request',
                                'requestable_id'   => $record->id,
                                'snapshot_id'      => $record->approvalSnapshot?->id,
                                'activity_id'      => $record->approvalSnapshot?->activity?->id,
                                'action'           => 'cancelled',
                                'actor_id'         => Auth::id(),
                                'actor_name'       => Auth::user()->name,
                                'note'             => 'Pengajuan dibatalkan oleh pemohon',
                            ]);

                            if ($record->approvalSnapshot) {
                                $record->approvalSnapshot->update(['status' => 'rejected']);
                            }
                        });

                        Notification::make()
                            ->title('Pengajuan Berhasil Dibatalkan')
                            ->success()
                            ->send();

                        $this->redirect(UserShiftSwapResource::getUrl('index'));

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal membatalkan pengajuan')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(UserShiftSwapResource::getUrl('index')),
        ];
    }
}
