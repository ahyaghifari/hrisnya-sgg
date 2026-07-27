<?php

namespace App\Filament\Resources\Master\EmployeeTypes\Tables;

use App\Filament\Resources\Master\EmployeeTypes\EmployeeTypeResource;
use App\Filament\Resources\Master\EmployeeTypes\Schemas\EmployeeTypeForm;
use App\Helpers\PermissionHelper;
use App\Models\Master\EmployeeTypes;
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

class EmployeeTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Tipe Karyawan')->searchable()->sortable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->placeholder('-')
                    ->color(fn ($state) => $state?->value === 'orientasi' ? 'warning' : 'gray'),

                TextColumn::make('user_profiles_count')
                    ->label('Jumlah Karyawan')
                    ->counts('userProfiles')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
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
                    ->modalHeading('Edit Tipe Karyawan')
                    ->visible(fn ($record) => EmployeeTypeResource::canEdit($record))
                    ->schema(fn (Schema $schema) => EmployeeTypeForm::configure($schema)->getComponents())
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('edit-action')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Update Data')
                            ->modalDescription('Apakah Anda yakin ingin memperbarui data ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();
                            
                                EmployeeTypes::find($data['id'])->update($data);
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            })
                            ->successRedirectUrl(fn () => EmployeeTypeResource::getUrl('index')),

                        Action::make('cancel')
                            ->label('Batal')
                            ->color('gray')
                        ]),

                DeleteAction::make()
                    ->visible(fn ($record) => EmployeeTypeResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
