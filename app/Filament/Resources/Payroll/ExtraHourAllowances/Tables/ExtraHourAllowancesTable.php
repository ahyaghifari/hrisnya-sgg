<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class ExtraHourAllowancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),

                TextColumn::make('allowanceable_type')
                    ->label('Tipe')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        JobPositions::class => 'Jabatan',
                        OrgStructure::class => 'Divisi',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        JobPositions::class => 'primary',
                        OrgStructure::class => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('allowanceable.name')
                    ->label('Target Tunjangan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount_per_hour')
                    ->label('Tunjangan Per Jam (Rp)')
                    ->formatStateUsing(fn ($state) => 'Rp ' . Number::format($state, locale: 'id'))
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

                SelectFilter::make('allowanceable_type')
                    ->label('Tipe Tunjangan')
                    ->options([
                        JobPositions::class => 'Jabatan',
                        EmployeeTypes::class => 'Tipe Karyawan',
                    ]),
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
