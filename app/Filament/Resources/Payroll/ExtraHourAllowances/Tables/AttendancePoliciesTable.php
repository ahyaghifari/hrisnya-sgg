<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Attendance\AttendancePolicies;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AttendancePoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                AttendancePolicies::query()
                    ->when(
                        ! PermissionHelper::hasGlobalRole(Auth::user()),
                        fn ($q) => $q->where('company_id', Auth::user()->company_id)
                    )
            )
            ->heading('Kebijakan Kehadiran')
            ->columns([
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),

                TextColumn::make('name')
                    ->label('Nama Kebijakan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('value')
                    ->label('Nilai')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->modalWidth('md')
                    ->modalHeading('Edit Kebijakan Kehadiran')
                    ->schema(fn (Schema $schema) => AttendancePoliciesForm::configure($schema)->getComponents())
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('save-policy')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Update Data')
                            ->modalDescription('Apakah Anda yakin ingin memperbarui kebijakan kehadiran ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();

                                AttendancePolicies::find($data['id'])->update($data);

                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            }),

                        Action::make('cancel-policy')
                            ->label('Batal')
                            ->color('gray'),
                    ]),
            ])
            ->toolbarActions([]);
    }
}
