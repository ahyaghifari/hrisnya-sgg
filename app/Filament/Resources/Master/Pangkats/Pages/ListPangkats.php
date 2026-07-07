<?php

namespace App\Filament\Resources\Master\Pangkats\Pages;

use App\Filament\Resources\Master\Pangkats\PangkatsResource;
use App\Filament\Resources\Master\Pangkats\Schemas\PangkatsForm;
use App\Models\Master\Companies;
use App\Models\Master\Pangkats;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\form;

class ListPangkats extends ListRecords
{
    protected static string $resource = PangkatsResource::class;
    protected static ?string $title = 'Daftar Pangkat';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Pangkat Baru')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Buat Pangkat Baru')
                ->visible(fn () => PangkatsResource::canCreate())
                ->schema(fn (Schema $schema) => PangkatsForm::configure($schema)->getComponents())
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
                                    'name' => 'required|string',
                                ], [
                                    'name.required' => 'Nama Pangkat wajib diisi.',
                                ])->validate();
                            
                                Pangkats::create($data);
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
                            }
                        }),

                    Action::make('cancel')
                        ->label('Batal')
                        ->color('gray')
                ])
        ];
    }
}
