<?php

namespace App\Filament\Resources\LMS\TrainerScoreVariables\Pages;

use App\Filament\Resources\LMS\TrainerScoreVariables\TrainerScoreVariableResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainerScoreVariable extends CreateRecord
{
    protected static string $resource = TrainerScoreVariableResource::class;

    protected static ?string $title = 'Tambah Variabel Gaji Trainer';

    public function getredirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Simpan')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                ->action(function () {
                    $this->create();
                    $this->redirect($this->getRedirectUrl());
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
