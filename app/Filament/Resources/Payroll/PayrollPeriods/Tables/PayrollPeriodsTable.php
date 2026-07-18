<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Tables;

use App\Jobs\GeneratePayrollJob;
use App\Jobs\RegeneratePayrollJob;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables;

class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Dari Tanggal')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Sampai Tanggal')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Status Generate')
                    ->formatStateUsing(fn ($state) => $state ? $state->translatedFormat('d M Y H:i') : 'Belum digenerate')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('update')
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
                        GeneratePayrollJob::dispatch($record->id, auth()->id());

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
                    ->visible(fn ($record) => ! is_null($record->processed_at))
                    ->action(function ($record) {
                        RegeneratePayrollJob::dispatch($record->id, auth()->id());

                        Notification::make()
                            ->title('Payroll regeneration started')
                            ->body('Unpaid payrolls are being re-generated in the background.')
                            ->warning()
                            ->send();
                    }),
            ]);
    }
}
