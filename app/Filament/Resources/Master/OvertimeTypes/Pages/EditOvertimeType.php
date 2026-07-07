<?php

namespace App\Filament\Resources\Master\OvertimeTypes\Pages;

use App\Filament\Resources\Master\OvertimeTypes\OvertimeTypeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOvertimeType extends EditRecord
{
    protected static string $resource = OvertimeTypeResource::class;
    protected static ?string $title = 'Edit Tipe Lembur';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getFormActions(): array
    {
        return [
            Action::make('edit')
                ->label('Simpan Perubahan')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Perubahan')
                ->modalDescription('Apakah Anda yakin ingin menyimpan perubahan ini?')
                ->action(function () {
                    $this->handleRecordUpdate($this->record, $this->data);

                    Notification::make()
                        ->title('Data berhasil diupdate')
                        ->success()
                        ->send();

                    $this->redirect($this->getRedirectUrl(), true);
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
