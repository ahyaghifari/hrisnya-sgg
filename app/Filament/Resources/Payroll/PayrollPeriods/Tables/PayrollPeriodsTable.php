<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Tables;

use App\Jobs\GeneratePayrollJob;
use App\Jobs\RegeneratePayrollJob;
use App\Models\Payroll\Payroll;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Support\Facades\DB;

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

                // Bersih total: hapus payrolls (payroll_items ikut cascade DB) +
                // payroll_manual_lines (cascade DB dari payroll_period_id) + periode-nya
                // sendiri. Ditolak kalau ada payroll is_paid=true di periode ini — payroll yang
                // sudah dibayar tidak boleh disentuh, prinsip yang sama dipakai regenerate.
                Action::make('deletePeriod')
                    ->label('Hapus Periode')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Periode Payroll')
                    ->modalDescription('Ini akan menghapus PERMANEN periode ini beserta semua payroll, item, dan manual line (Koperasi/Bon/Insentif) di dalamnya. Tidak bisa dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus Semua')
                    ->visible(fn ($record) => ! $record->payrolls()->where('is_paid', true)->exists())
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            Payroll::where('payroll_period_id', $record->id)->each(fn ($payroll) => $payroll->delete());
                            $record->delete();
                        });

                        Notification::make()
                            ->title('Periode berhasil dihapus')
                            ->body('Payroll, item, dan manual line di periode ini sudah ikut terhapus.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
