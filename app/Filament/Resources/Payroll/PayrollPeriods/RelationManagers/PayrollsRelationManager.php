<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\RelationManagers;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Resources\Payroll\Payrolls\PayrollResource;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
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
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('viewPayroll')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn ($record) =>
                    PayrollResource::getUrl('view', ['record' => $record])
                    ),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
