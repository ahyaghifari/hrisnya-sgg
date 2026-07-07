<?php

namespace App\Filament\Resources\Master\Golongans\Tables;

use App\Filament\Resources\Master\Golongans\GolongansResource;
use App\Filament\Resources\Master\Golongans\Schemas\GolongansForm;
use App\Helpers\PermissionHelper;
use App\Models\Master\Golongans;
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

class GolongansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Golongan')->searchable()->sortable(),
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
                    ->modalHeading('Edit Golongan')
                    ->visible(fn ($record) => GolongansResource::canEdit($record))
                    ->schema(fn (Schema $schema) => GolongansForm::configure($schema)->getComponents())
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('edit-action')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Update Data')
                            ->modalDescription('Apakah Anda yakin ingin memperbarui data ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();
                            
                                Golongans::find($data['id'])->update($data);
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            })
                            ->successRedirectUrl(fn () => GolongansResource::getUrl('index')),

                        Action::make('cancel')
                            ->label('Batal')
                            ->color('gray')
                        ]),

                DeleteAction::make()
                    ->visible(fn ($record) => GolongansResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
