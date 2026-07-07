<?php

namespace App\Filament\Resources\Payroll\ProgramIncentives\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Incentive\ProgramIncentive;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProgramIncentivesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Program Holder')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('program_name')
                    ->label('Program')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('incentive_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('IDR', true)
                    ->sortable(),
                IconColumn::make('is_processed')
                    ->label('Processed')
                    ->boolean(),
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
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('md'),

                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md')
                    ->visible(fn (ProgramIncentive $record) => ! $record->is_processed),

                DeleteAction::make()
                    ->slideOver()
                    ->modalWidth('md')
                    ->visible(fn (ProgramIncentive $record) => ! $record->is_processed),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
