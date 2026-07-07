<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\RelationManagers;

use App\Models\Payroll\DoctorPayroll;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    protected static ?string $title = 'Doctor Payrolls';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('doctor.name')
            ->columns([
                TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gross_salary')
                    ->money('IDR', true)
                    ->sortable(),

                TextColumn::make('tax_percentage')
                    ->label('Tax (%)')
                    ->formatStateUsing(fn ($state) => $state . '%'),

                TextColumn::make('tax_amount')
                    ->money('IDR', true)
                    ->sortable(),

                TextColumn::make('net_salary')
                    ->money('IDR', true)
                    ->color('success')
                    ->sortable(),

                IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalWidth('xl')
                    ->infolist([
                        Section::make('Doctor & Period')
                            ->schema([
                                TextEntry::make('doctor.name')
                                    ->label('Doctor'),

                                TextEntry::make('period.start_date')
                                    ->label('Period Start')
                                    ->date(),

                                TextEntry::make('period.end_date')
                                    ->label('Period End')
                                    ->date(),

                                TextEntry::make('is_paid')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (bool $state) => $state ? 'success' : 'gray')
                                    ->formatStateUsing(fn (bool $state) => $state ? 'Paid' : 'Unpaid'),

                                TextEntry::make('paid_at')
                                    ->label('Paid At')
                                    ->dateTime()
                                    ->placeholder('-'),
                            ])
                            ->columns(2),

                        Section::make('Doctor Fee Items')
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('service_name')
                                            ->label('Service'),

                                        TextEntry::make('amount')
                                            ->label('Amount')
                                            ->money('IDR', true),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Payroll Breakdown')
                            ->schema([
                                TextEntry::make('gross_salary')
                                    ->label('Gross Salary')
                                    ->money('IDR', true),

                                TextEntry::make('tax_percentage')
                                    ->label('Tax (%)')
                                    ->formatStateUsing(fn ($state) => $state . '%'),

                                TextEntry::make('tax_amount')
                                    ->label('Tax Amount')
                                    ->money('IDR', true)
                                    ->color('danger'),

                                TextEntry::make('net_salary')
                                    ->label('Net Salary')
                                    ->money('IDR', true)
                                    ->weight('bold')
                                    ->color('success'),
                            ])
                            ->columns(2),
                    ])->modalSubmitAction(false),
                Action::make('markAsPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DoctorPayroll $record) => ! $record->is_paid)
                    ->action(function (DoctorPayroll $record) {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'is_paid' => true,
                                'paid_at' => now(),
                            ]);
                        });

                        Notification::make()
                            ->title('Payroll marked as paid')
                            ->success()
                            ->send();
                    }),

                Action::make('downloadSlip')
                    ->label('Download Slip')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn (DoctorPayroll $record) => $record->is_paid)
                    ->action(fn (DoctorPayroll $record) =>
                    app(\App\Services\Payroll\DoctorPayrollService::class)
                        ->download($record)
                    ),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
