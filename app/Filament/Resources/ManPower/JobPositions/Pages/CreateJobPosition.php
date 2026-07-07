<?php

namespace App\Filament\Resources\ManPower\JobPositions\Pages;

use App\Enum\JobPositionReqStatus;
use App\Filament\Resources\ManPower\JobPositions\JobPositionResource;
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
            Action::make('create_request')
                ->label('Simpan dan Ajukan')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                ->tooltip('Data akan disimpan dan diajukan untuk proses persetujuan.')
                ->action(function () {
                    $this->create();
                    app(JobPositionService::class)->requestJobPosition($this->record, JobPositionReqStatus::REQUESTED->value);

                    Notification::make()
                        ->title('Data berhasil disimpan')
                        ->success()
                        ->send();

                    // $this->redirect($this->getRedirectUrl());
                }),

            Action::make('publish')
                ->label('Simpan dan Publish')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan dan Publish')
                ->modalDescription('Apakah Anda yakin ingin menyimpan dan mempublish data ini? Status akan langsung disetujui.')
                ->tooltip('Data akan disimpan dan langsung disetujui tanpa melalui proses persetujuan.')
                ->action(function () {
                    $this->create();
                    $this->record->update([
                        'status' => JobPositionReqStatus::APPROVED->value,
                        'actual_manpower' => $this->record->planned_manpower,
                        'request_type' => 'published',
                        'is_draft' => false,
                    ]);

                    Notification::make()
                        ->title('Data berhasil disimpan dan dipublish')
                        ->success()
                        ->send();

                    // $this->redirect($this->getRedirectUrl());
                }),

            Action::make('create_draft')
                ->label('Simpan Draft')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini dan membuat entri baru?')
                ->tooltip('Data akan disimpan sebagai draft dan belum diajukan untuk persetujuan.')
                ->action(function () {
                    $this->create();

                    Notification::make()
                        ->title('Data berhasil disimpan')
                        ->success()
                        ->send();
                        
                    // $this->redirect($this->getRedirectUrl());
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
