<?php

namespace App\Filament\Resources\Master\EmployeeTypes\Pages;

use App\Filament\Resources\Master\EmployeeTypes\EmployeeTypeResource;
use App\Filament\Resources\Master\EmployeeTypes\Schemas\EmployeeTypeForm;
use App\Models\Master\EmployeeTypes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListEmployeeTypes extends ListRecords
{
    protected static string $resource = EmployeeTypeResource::class;
    protected static ?string $title = 'Daftar Tipe Karyawan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Pangkat Baru')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Buat Pangkat Baru')
                ->visible(fn () => EmployeeTypeResource::canCreate())
                ->schema(fn (Schema $schema) => EmployeeTypeForm::configure($schema)->getComponents())
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('create-action')
                        ->label('Simpan')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Simpan Data')
                        ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                        ->action(function (array $mountedActions) {
                            $data = $mountedActions[0]->getRawData();
                        
                            EmployeeTypes::create($data);
                            $this->redirect($this->getResource()::getUrl('index'));

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
