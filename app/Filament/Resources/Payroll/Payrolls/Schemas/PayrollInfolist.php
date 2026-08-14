<?php

namespace App\Filament\Resources\Payroll\Payrolls\Schemas;

use App\Models\AuditLog;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('user.name')
                                ->label('Employee'),

                            TextEntry::make('user.nip')
                                ->label('NIP'),

                            TextEntry::make('user.company.name')
                                ->label('Company'),
                        ]),
                    ])->columnSpanFull(),

                Section::make('Payroll Period')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('payrollPeriod.start_date')
                                ->label('Start Date')
                                ->date(),

                            TextEntry::make('payrollPeriod.end_date')
                                ->label('End Date')
                                ->date(),

                            TextEntry::make('is_paid')
                                ->label('Payment Status')
                                ->badge()
                                ->color(fn ($state) => $state ? 'success' : 'warning')
                                ->formatStateUsing(fn ($state) => $state ? 'Paid' : 'Unpaid'),
                        ]),
                    ]),

                Section::make('Salary Summary')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('gross_salary')
                                ->label('Gross Salary')
                                ->money('IDR', true),

                            TextEntry::make('total_deductions')
                                ->label('Total Deductions')
                                ->money('IDR', true)
                                ->color('danger'),

                            TextEntry::make('net_salary')
                                ->label('Net Salary')
                                ->money('IDR', true)
                                ->weight('bold')
                                ->color('success'),
                        ]),
                    ]),

                Section::make('Riwayat Pembayaran')
                    ->schema([
                        RepeatableEntry::make('paymentHistory')
                            ->label('')
                            ->state(fn ($record) => AuditLog::where('key', 'payroll:' . $record->id)
                                ->where('module', 'payroll_payment')
                                ->orderByDesc('created_at')
                                ->with('user')
                                ->get())
                            ->schema([
                                TextEntry::make('new_value')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn ($state) => $state === 'Sudah Dibayar' ? 'success' : 'danger'),

                                TextEntry::make('user.name')
                                    ->label('Oleh'),

                                TextEntry::make('created_at')
                                    ->label('Waktu')
                                    ->dateTime('d M Y H:i'),

                                TextEntry::make('label')
                                    ->label('Catatan')
                                    ->formatStateUsing(fn ($record) => $record->reasonFromLabel() ?? '-')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3),
                    ])
                    ->visible(fn ($record) => AuditLog::where('key', 'payroll:' . $record->id)->where('module', 'payroll_payment')->exists()),
            ]);
    }
}
