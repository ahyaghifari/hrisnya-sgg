<?php

namespace App\Filament\Resources\Master\JobPositions\Pages;

use App\Enum\JobPositionReqStatus;
use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use App\Services\JobPosition\JobPositionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateJobPosition extends CreateRecord
{
    protected static string $resource = JobPositionResource::class;
    protected static ?string $title = 'Buat Jabatan Baru';
    
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
                ->tooltip('Data akan disimpan dan diajukan untuk proses persetujuan.')
                ->action(function () {
                    $this->create();

                    Notification::make()
                        ->title('Data berhasil disimpan')
                        ->success()
                        ->send();

                    $this->redirect($this->getRedirectUrl());
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
