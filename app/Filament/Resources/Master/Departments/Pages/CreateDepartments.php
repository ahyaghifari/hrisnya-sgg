<?php

namespace App\Filament\Resources\Master\Departments\Pages;

use App\Filament\Resources\Master\Departments\DepartmentsResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartments extends CreateRecord
{
    protected static string $resource = DepartmentsResource::class;

    protected static ?string $title = "Buat Departemen";

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
                    try {
                        $this->form->validate();
                        
                        $this->handleRecordCreation($this->data);

                        Notification::make()
                            ->title('Data berhasil disimpan')
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

            Action::make('create_and_create_another')
                ->label('Simpan dan Buat Baru')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini dan membuat entri baru?')
                ->action(function () {
                    try {
                        $this->form->validate();
                        
                        $this->handleRecordCreation($this->data);
    
                        Notification::make()
                            ->title('Data berhasil disimpan')
                            ->success()
                            ->send();
    
                        $this->form->fill([]);
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
