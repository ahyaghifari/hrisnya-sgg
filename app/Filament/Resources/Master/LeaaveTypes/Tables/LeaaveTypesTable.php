<?php

namespace App\Filament\Resources\Master\LeaaveTypes\Tables;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Filament\Resources\Master\LeaaveTypes\LeaaveTypeResource;
use App\Filament\Resources\Master\LeaaveTypes\Schemas\LeaaveTypeForm;
use App\Helpers\PermissionHelper;
use App\Models\Master\Attendance\LeaveTypes;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LeaaveTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Tipe Cuti')->searchable()->sortable(),
                TextColumn::make('category')->label('Kategori')->placeholder('-')
                    ->formatStateUsing(fn (?string $state) => $state ? LeaveTypeCategoryEnum::tryFrom($state)?->label() ?? $state : null),
                TextColumn::make('quota_days')->label('Kuota (Hari)')->searchable()->sortable(),
                TextColumn::make('reset_period')->label('Periode Reset')->searchable()->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'yearly' => 'Tahunan',
                        'monthly' => 'Bulanan',
                        'none' => 'Tidak Ada',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalHeading('Edit Data')
                    ->modalWidth('md')
                    ->schema(fn (Schema $schema) => LeaaveTypeForm::configure($schema)->getComponents())
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('edit-action')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Update Data')
                            ->modalDescription('Apakah Anda yakin ingin memperbarui data ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();
                            
                                LeaveTypes::find($data['id'])->update($data);
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            })
                            ->successRedirectUrl(fn () => LeaaveTypeResource::getUrl('index')),

                        Action::make('cancel')
                            ->label('Batal')
                            ->color('gray')
                        ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
