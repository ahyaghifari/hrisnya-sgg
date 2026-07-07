<?php

namespace App\Filament\Resources\Master\LeaaveTypes\Pages;

use App\Filament\Resources\Master\LeaaveTypes\LeaaveTypeResource;
use App\Filament\Resources\Master\LeaaveTypes\Schemas\LeaaveTypeForm;
use App\Models\Master\Attendance\LeaveTypes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListLeaaveTypes extends ListRecords
{
    protected static string $resource = LeaaveTypeResource::class;
    protected static ?string $title = 'Daftar Jenis Cuti';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Jenis Cuti')
                ->slideOver()
                ->modalHeading('Tambah Tipe Cuti')
                ->modalWidth('md')
                ->schema(fn (Schema $schema) => LeaaveTypeForm::configure($schema)->getComponents())
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('create-action')
                        ->label('Simpan')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Simpan Data')
                        ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                        ->action(function (array $mountedActions) {
                            $data = $mountedActions[0]->getRawData();
                        
                            LeaveTypes::create($data);
                            $this->redirect($this->getResource()::getUrl('index'), true);

                            Notification::make()
                                ->title('Data berhasil disimpan')
                                ->success()
                                ->send();
                        }),

                    Action::make('cancel')
                        ->label('Batal')
                        ->color('gray')
                ])
        ];
    }
}
