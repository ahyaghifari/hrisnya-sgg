<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Tables;

use App\Jobs\GeneratePayrollJob;
use App\Jobs\RegeneratePayrollJob;
use App\Models\Payroll\PayrollPeriod;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables;

class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\IconColumn::make('processed_at')
                    ->label('Generated')
                    ->boolean(fn ($record) => ! is_null($record->processed_at)),
            ])
            ->filters([
                //
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
                    ->modalDescription('This will generate payroll for all active users in this company. This action cannot be undone.')
                    ->visible(fn ($record) => is_null($record->processed_at))
                    ->action(function ($record) {
                        GeneratePayrollJob::dispatch($record->id);

                        Notification::make()
                            ->title('Payroll generation started')
                            ->body('Payroll is being processed in background.')
                            ->success()
                            ->send();
                    }),

                Action::make('regeneratePayroll')
                    ->label('Regenerate Payroll')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate Payroll')
                    ->modalDescription('This will delete and re-generate all unpaid payrolls for this period. Payrolls that have already been paid will not be affected.')
                    ->action(function ($record) {
                        RegeneratePayrollJob::dispatch($record->id);

                        Notification::make()
                            ->title('Payroll regeneration started')
                            ->body('Unpaid payrolls are being re-generated in the background.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->visible(fn ($records) =>
                    $records->every(fn ($record) => is_null($record->processed_at))
                    ),
            ]);
    }
}
