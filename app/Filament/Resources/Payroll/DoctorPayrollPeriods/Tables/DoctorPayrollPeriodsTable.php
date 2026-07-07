<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\Tables;

use App\Helpers\PermissionHelper;
use App\Jobs\GenerateDoctorPayrollJob;
use App\Models\Payroll\DoctorPayrollPeriod;
use App\Models\Payroll\PayrollPeriod;
use App\Services\Payroll\DoctorPayrollService;
use App\Services\Payroll\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DoctorPayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Company')
                    ->placeholder('All Companies')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Start')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End')
                    ->date()
                    ->sortable(),

                TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime()
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
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
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => is_null($record->processed_at)),
                Action::make('generatePayroll')
                    ->label('Generate Payroll')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Payroll')
                    ->modalDescription('This will generate doctor payroll for all active users in this company. This action cannot be undone.')
                    ->visible(fn ($record) => is_null($record->processed_at))
                    ->action(function ($record) {
                        GenerateDoctorPayrollJob::dispatch($record->id);

//                        $service = new DoctorPayrollService;
//                        $period = DoctorPayrollPeriod::findOrFail($record->id);
//                        $service->generatePayroll($period);

                        Notification::make()
                            ->title('Doctor Payroll generation started')
                            ->body('Doctor Payroll is being processed in background.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records, $action) {
                            if ($records->contains(fn ($r) => !is_null($r->processed_at))) {
                                Notification::make()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Payroll yang sudah diproses tidak bisa dihapus.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }
}
