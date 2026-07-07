<?php

namespace App\Filament\Resources\Master\Banks\Tables;

use App\Filament\Resources\Master\Banks\BanksResource;
use App\Filament\Resources\Master\Banks\Schemas\BanksForm;
use App\Helpers\PermissionHelper;
use App\Models\Master\Banks;
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
use PHPUnit\Event\Code\TestCollection;

class BanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode Bank')->sortable()->searchable(),
                TextColumn::make('name')->label('Nama Bank')->sortable()->searchable()
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
                    ->modalHeading('Edit Data Bank')
                    ->visible(fn ($record) => BanksResource::canEdit($record))
                    ->schema(fn (Schema $schema) => BanksForm::configure($schema)->getComponents())
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('edit-action')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Update Data')
                            ->modalDescription('Apakah Anda yakin ingin memperbarui data ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();
                            
                                Banks::find($data['id'])->update($data);
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            })
                            ->successRedirectUrl(fn () => BanksResource ::getUrl('index')),

                        Action::make('cancel')
                            ->label('Batal')
                            ->color('gray')
                        ]),

                DeleteAction::make()
                    ->visible(fn ($record) => BanksResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
