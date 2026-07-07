<?php

namespace App\Filament\Resources\Master\Departments\Pages;

use App\Filament\Resources\Master\Departments\DepartmentsResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDepartments extends EditRecord
{
    protected static string $resource = DepartmentsResource::class;

    protected static ?string $title = "Edit Departemen";

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn ($record) => DepartmentsResource::canDelete($record)),
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
                    try {
                        $this->form->validate();

                        $this->handleRecordUpdate($this->record, $this->data);
    
                        Notification::make()
                            ->title('Data berhasil diupdate')
                            ->success()
                            ->send();
    
                        $this->redirect($this->getRedirectUrl());
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Notification::make()
                            ->title('Validasi Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
