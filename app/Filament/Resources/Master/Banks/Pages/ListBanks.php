<?php

namespace App\Filament\Resources\Master\Banks\Pages;

use App\Filament\Resources\Master\Banks\BanksResource;
use App\Filament\Resources\Master\Banks\Schemas\BanksForm;
use App\Models\Master\Banks;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListBanks extends ListRecords
{
    protected static string $resource = BanksResource::class;
    protected static ?string $title = 'Daftar Bank';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Bank')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Tambah Data Bank')
                ->visible(fn () => BanksResource::canCreate())
                ->schema(fn (Schema $schema) => BanksForm::configure($schema)->getComponents())
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('create-action')
                        ->label('Simpan')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Simpan Data')
                        ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                        ->action(function (array $mountedActions) {
                            $data = $mountedActions[0]->getRawData();
                        
                            Banks::create($data);
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

