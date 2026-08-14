<?php

namespace App\Filament\Resources\Payroll\Payrolls\Tables;

use App\Helpers\PermissionHelper;
use App\Models\AuditLog;
use App\Models\Payroll\Payroll;
use App\Services\Payroll\PayrollService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payrollPeriod.start_date')
                    ->label('Period')
                    ->formatStateUsing(fn ($record) =>
                        Carbon::parse($record->payrollPeriod->start_date)->format('d M Y')
                        . ' - ' .
                        Carbon::parse($record->payrollPeriod->end_date)->format('d M Y')
                    ),

                TextColumn::make('gross_salary')
                    ->money('IDR', true),

                TextColumn::make('total_deductions')
                    ->money('IDR', true),

                TextColumn::make('net_salary')
                    ->money('IDR', true)
                    ->weight('bold'),

                IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('payrollPeriod.company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () =>
                    PermissionHelper::hasGlobalRole(Auth::user())
                        ? Auth::user()->company_id
                        : null
                    )
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('markAsPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => ! $record->is_paid)
                    ->action(function (Payroll $record) {
                        $record->update(['is_paid' => true]);

                        AuditLog::record(
                            'payroll_payment',
                            $record->payrollPeriod->company_id,
                            'payroll:' . $record->id,
                            $record->user->name . ' — ' . Carbon::parse($record->payrollPeriod->start_date)->format('M Y'),
                            'Belum Dibayar',
                            'Sudah Dibayar'
                        );
                    }),

                Action::make('cancelPaid')
                    ->label('Batalkan Pembayaran')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalDescription('Notifikasi "Gaji Sudah Dibayar" yang sudah terkirim ke karyawan TIDAK ditarik balik - aksi ini cuma membalikkan status Paid dan tercatat di audit log.')
                    ->visible(fn (Payroll $record) => $record->is_paid)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Catatan (alasan pembatalan)')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Payroll $record, array $data) {
                        $record->update(['is_paid' => false]);

                        AuditLog::record(
                            'payroll_payment',
                            $record->payrollPeriod->company_id,
                            'payroll:' . $record->id,
                            $record->user->name . ' — ' . Carbon::parse($record->payrollPeriod->start_date)->format('M Y') . ' (' . $data['reason'] . ')',
                            'Sudah Dibayar',
                            'Dibatalkan'
                        );
                    }),

                Action::make('downloadSlip')
                    ->label('Download Slip')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn (Payroll $record) => $record->is_paid)
                    ->action(fn (Payroll $record) =>
                    app(PayrollService::class)
                        ->download($record)
                    ),
            ])
            ->bulkActions([]);
    }
}
