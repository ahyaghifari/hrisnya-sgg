<?php

namespace App\Filament\Resources\Master\Pangkats\Tables;

use App\Filament\Resources\Master\Pangkats\PangkatsResource;
use App\Filament\Resources\Master\Pangkats\Schemas\PangkatsForm;
use App\Helpers\PermissionHelper;
use App\Models\Master\Pangkats;
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

class PangkatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Pangkat')->searchable()->sortable(),
                TextColumn::make('jobPositions.name')
                    ->label('Posisi')
                    ->badge()
                    ->separator(', '),
                TextColumn::make('employee_count')
                    ->label('Jumlah Karyawan')
                    ->getStateUsing(fn ($record) => $record->userProfiles()->count()),
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
                EditAction::make('edit')
                    ->label('Edit')
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Edit Pangkat')
                    ->visible(fn ($record) => PangkatsResource::canEdit($record))
                    ->schema(fn (Schema $schema) => PangkatsForm::configure($schema)->getComponents())
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('edit-action')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Update Data')
                            ->modalDescription('Apakah Anda yakin ingin memperbarui data ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();
                            
                                Pangkats::find($data['id'])->update($data);
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            })
                            ->successRedirectUrl(fn () => PangkatsResource::getUrl('index')),

                        Action::make('cancel')
                            ->label('Batal')
                            ->color('gray')
                        ]),

                DeleteAction::make()
                    ->visible(fn ($record) => PangkatsResource::canDelete($record) && $record->jobPositions()->doesntExist()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
