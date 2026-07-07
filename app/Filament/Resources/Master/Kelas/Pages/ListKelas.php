<?php

namespace App\Filament\Resources\Master\Kelas\Pages;

use App\Filament\Resources\Master\Kelas\KelasResource;
use App\Filament\Resources\Master\Kelas\Schemas\KelasForm;
use App\Models\Master\Kelas;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListKelas extends ListRecords
{
    protected static string $resource = KelasResource::class;
    protected static ?string $title = 'Daftar Kelas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Kelas Baru')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Buat Kelas Baru')
                ->visible(fn () => KelasResource::canCreate())
                ->schema(fn (Schema $schema) => KelasForm::configure($schema)->getComponents())
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
                                    'name.required' => 'Nama Kelas wajib diisi.',
                                ])->validate();
                            
                                Kelas::create($data);
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
