<?php

namespace App\Filament\Resources\Master\JobPositions\Pages;

use App\Enum\JobPositionReqStatus;
use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use App\Helpers\PermissionHelper;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\User;
use App\Services\JobPosition\JobPositionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditJobPosition extends EditRecord
{
    protected static string $resource = JobPositionResource::class;
    protected static ?string $title = 'Edit Jabatan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => JobPositionResource::canDelete($this->record)),
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
                ->label('Simpan')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Draft')
                ->modalDescription('Apakah Anda yakin ingin menyimpan perubahan ini?')
                ->action(function () {
                    $this->handleRecordUpdate($this->record, $this->data);

                    Notification::make()
                        ->title('Data berhasil diupdate')
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
