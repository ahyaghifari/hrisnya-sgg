<?php

namespace App\Filament\Resources\Master\Religions\Pages;

use App\Filament\Resources\Master\Religions\ReligionsResource;
use App\Filament\Resources\Master\Religions\Schemas\ReligionsForm;
use App\Models\Master\Religions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListReligions extends ListRecords
{
    protected static string $resource = ReligionsResource::class;

    protected static ?string $title = "Data Agama";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Pangkat Baru')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Buat Pangkat Baru')
                ->visible(fn () => ReligionsResource::canCreate())
                ->schema(fn (Schema $schema) => ReligionsForm::configure($schema)->getComponents())
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('create-action')
                        ->label('Simpan')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Simpan Data')
                        ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                        ->action(function (array $mountedActions) {
                            $data = $mountedActions[0]->getRawData();
                        
                            Religions::create($data);
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
