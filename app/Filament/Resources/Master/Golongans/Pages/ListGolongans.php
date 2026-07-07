<?php

namespace App\Filament\Resources\Master\Golongans\Pages;

use App\Filament\Resources\Master\Golongans\GolongansResource;
use App\Filament\Resources\Master\Golongans\Schemas\GolongansForm;
use App\Models\Master\Golongans;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListGolongans extends ListRecords
{
    protected static string $resource = GolongansResource::class;
    protected static ?string $title = 'Daftar Golongan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Golongan Baru')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Buat Golongan Baru')
                ->visible(fn () => GolongansResource::canCreate())
                ->schema(fn (Schema $schema) => GolongansForm::configure($schema)->getComponents())
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('create-action')
                        ->label('Simpan')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Simpan Data')
                        ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                        ->action(function (array $mountedActions) {
                            try {
                                $data = $mountedActions[0]->getRawData();

                                validator($data, [
                                    'name' => 'required|string|max:255',
                                ], [
                                    'name.required' => 'Nama Golongan wajib diisi.',
                                ])->validate();
                            
                                Golongans::create($data);
                                $this->redirect($this->getResource()::getUrl('index'));
    
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            } catch (\Illuminate\Validation\ValidationException $e) {
                                Notification::make()
                                    ->title('Validasi Gagal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }),

                    Action::make('cancel')
                        ->label('Batal')
                        ->color('gray')
                ])
        ];
    }
}
