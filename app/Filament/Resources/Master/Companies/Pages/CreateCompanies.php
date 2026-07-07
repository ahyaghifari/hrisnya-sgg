<?php

namespace App\Filament\Resources\Master\Companies\Pages;

use App\Filament\Resources\Master\Companies\CompaniesResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCompanies extends CreateRecord
{
    protected static string $resource = CompaniesResource::class;

    protected static ?string $title = "Buat Perusahaan Baru";

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Simpan')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                ->action(function () {
                    $this->handleRecordCreation($this->data);

                    Notification::make()
                        ->title('Data berhasil disimpan')
                        ->success()
                        ->send();

                    $this->redirect($this->getRedirectUrl(), true);
                }),

            Action::make('create_and_create_another')
                ->label('Simpan dan Buat Baru')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini dan membuat entri baru?')
                ->action(function () {
                    $this->handleRecordCreation($this->data);

                    Notification::make()
                        ->title('Data berhasil disimpan')
                        ->success()
                        ->send();

                    $this->form->fill([]);
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
