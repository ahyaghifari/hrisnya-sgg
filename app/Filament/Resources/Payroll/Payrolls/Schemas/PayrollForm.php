<?php

namespace App\Filament\Resources\Payroll\Payrolls\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollForm
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
            ]);
    }
}
