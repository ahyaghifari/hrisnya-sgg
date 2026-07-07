<?php

namespace App\Filament\Resources\Payroll\OvertimeRates\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OvertimeRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shift.name')
                    ->label('Shift')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('overtimeType.name')
                    ->label('Jenis Lembur')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('maximum_overtime_hours')
                    ->label('Maks. Jam Lembur')
                    ->suffix(' jam')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('overtime_rate')
                    ->label('Tarif Lembur')
                    ->money('IDR')
                    ->placeholder('-')
                    ->sortable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
