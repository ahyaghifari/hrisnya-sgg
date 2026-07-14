<?php

namespace App\Filament\Resources\LMS\TrainerScoreVariables\Pages;

use App\Filament\Resources\LMS\TrainerScoreVariables\TrainerScoreVariableResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainerScoreVariable extends EditRecord
{
    protected static string $resource = TrainerScoreVariableResource::class;
    
    protected static ?string $title = 'Edit Variabel Gaji Trainer';

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
                    $this->save();
                    $this->redirect($this->getRedirectUrl());
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
