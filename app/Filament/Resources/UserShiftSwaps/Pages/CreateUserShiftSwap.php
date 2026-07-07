<?php

namespace App\Filament\Resources\UserShiftSwaps\Pages;

use App\Filament\Resources\UserShiftSwaps\UserShiftSwapResource;
use App\Services\Shift\ShiftSwapRequestService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUserShiftSwap extends CreateRecord
{
    protected static string $resource = UserShiftSwapResource::class;

    public function getTitle(): string
    {
        return 'Ajukan Tukar Shift';
    }

    public function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Kirim Pengajuan')
                ->icon('heroicon-o-paper-airplane')
                ->action(function () {
                    $state = $this->form->getState();

                    try {
                        app(ShiftSwapRequestService::class)->store([
                            'req_schedule_id'    => $state['req_schedule_id'],
                            'swap_mode'          => $state['swap_mode'] ?? 'schedule',
                            'target_schedule_id' => $state['target_schedule_id'] ?? null,
                            'target_user_off'    => $state['target_user_off'] ?? null,
                            'notes'              => $state['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Pengajuan Tukar Shift Berhasil')
                            ->body('Pengajuan tukar shift Anda telah dikirim dan sedang menunggu persetujuan.')
                            ->success()
                            ->send();

                        $this->redirect(UserShiftSwapResource::getUrl('index'));

                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Pengajuan Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Konfigurasi Approval Belum Tersedia')
                            ->body($e->getMessage())
                            ->warning()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Terjadi Kesalahan')
                            ->body('Silakan coba lagi atau hubungi administrator.')
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url(UserShiftSwapResource::getUrl('index')),
        ];
    }
}
