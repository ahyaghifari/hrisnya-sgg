<?php

namespace App\Filament\Clusters\ShiftPattern\Widgets;

use App\Filament\Resources\Payroll\ExtraHourAllowances\Schemas\AttendancePoliciesForm;
use App\Models\Attendance\AttendancePolicies;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendancePoliciesWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = '';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AttendancePolicies::query()
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Nilai')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                // 
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
