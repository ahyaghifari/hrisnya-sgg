<?php

namespace App\Filament\Resources\ManPower\ManpowerRequests\Pages;

use App\Enum\JobPositionReqStatus;
use App\Filament\Resources\ManPower\ManpowerRequests\ManpowerRequestResource;
use App\Services\JobPosition\ManPowerReqService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateManpowerRequest extends CreateRecord
{
    public $type;
    protected static string $resource = ManpowerRequestResource::class;
    
    public function mount(): void
    {
        parent::mount();

        $this->type = request()->query('type');
        $this->form->fill([
            'type' => $this->type,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        $type = $this->type;

        if ($type === 'quota') {
            return 'Tambah Perubahan Kuota';
        } else if ($type === 'employee_change') {
            return 'Tambah Permintaan Tenaga Kerja';
        }

        return 'Tambah Perubahan Manpower';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['type' => $this->type]);
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
                    try {
                        $state = $this->form->getState();
                        $state['status'] = JobPositionReqStatus::REQUESTED->value;
                        $state['company_id'] = auth()->user()->company_id;
                        
                        $this->record = $this->handleRecordCreation($state);
                        app(ManPowerReqService::class)->requestManPower($this->record);
    
                        Notification::make()
                            ->title('Data berhasil disimpan')
                            ->success()
                            ->send();
    
                        $this->redirect($this->getRedirectUrl());
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }
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
                        
                    $this->redirect($this->getRedirectUrl());
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
