<?php

namespace App\Filament\Resources\Master\TaskServices\Pages;

use App\Filament\Resources\Master\TaskServices\Schemas\TaskServiceForm;
use App\Filament\Resources\Master\TaskServices\TaskServiceResource;
use App\Models\Master\Task\TaskService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListTaskServices extends ListRecords
{
    protected static string $resource = TaskServiceResource::class;
    protected static ?string $title = 'Daftar Layanan Penugasan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Layanan')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Tambah Data Layanan Penugasan')
                ->visible(fn () => TaskServiceResource::canCreate())
                ->schema(fn (Schema $schema) => TaskServiceForm::configure($schema)->getComponents())
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('create-action')
                        ->label('Simpan')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Simpan Data')
                        ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                        ->action(function (array $mountedActions) {
                            $data = $mountedActions[0]->getRawData();
                        
                            TaskService::create($data);
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
