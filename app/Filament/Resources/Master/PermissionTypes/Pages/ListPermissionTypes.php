<?php

namespace App\Filament\Resources\Master\PermissionTypes\Pages;

use App\Filament\Resources\Master\PermissionTypes\PermissionTypeResource;
use App\Filament\Resources\Master\PermissionTypes\Schemas\PermissionTypeForm;
use App\Models\Master\Attendance\PermissionTypes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListPermissionTypes extends ListRecords
{
    protected static string $resource = PermissionTypeResource::class;
    protected static ?string $title = 'Daftar Jenis Izin';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Jenis Izin')
                ->slideOver()
                ->modalHeading('Tambah Jenis Izin')
                ->modalWidth('md')
                ->schema(fn (Schema $schema) => PermissionTypeForm::configure($schema)->getComponents())
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('create-action')
                        ->label('Simpan')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Simpan Data')
                        ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                        ->action(function (array $mountedActions) {
                            $data = $mountedActions[0]->getRawData();
                        
                            PermissionTypes::create($data);
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
