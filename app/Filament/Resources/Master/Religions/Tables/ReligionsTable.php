<?php

namespace App\Filament\Resources\Master\Religions\Tables;

use App\Filament\Resources\Master\Religions\ReligionsResource;
use App\Filament\Resources\Master\Religions\Schemas\ReligionsForm;
use App\Helpers\PermissionHelper;
use App\Models\Master\Religions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReligionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("name")
                    ->label("Nama Agama")
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Edit Data Agama')
                    ->visible(fn ($record) => ReligionsResource::canEdit($record))
                    ->schema(fn (Schema $schema) => ReligionsForm::configure($schema)->getComponents())
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('edit-action')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Update Data')
                            ->modalDescription('Apakah Anda yakin ingin memperbarui data ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();
                            
                                Religions::find($data['id'])->update($data);
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            })
                            ->successRedirectUrl(fn () => ReligionsResource::getUrl('index')),

                        Action::make('cancel')
                            ->label('Batal')
                            ->color('gray')
                        ]),

                DeleteAction::make()
                    ->visible(fn ($record) => ReligionsResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
