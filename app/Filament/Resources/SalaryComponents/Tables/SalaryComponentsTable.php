<?php

namespace App\Filament\Resources\SalaryComponents\Tables;

use App\Enum\Salary\CalculationTypeEnum;
use App\Enum\Salary\SalaryComponentCategoryEnum;
use App\Models\SalaryComponent\SalaryComponents;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalaryComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('assignments')->with('employeeTypes'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->placeholder('Semua Perusahaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (SalaryComponentCategoryEnum::tryFrom($state)?->label() ?? $state) : '-')
                    ->color(fn (?string $state) => SalaryComponentCategoryEnum::tryFrom($state)?->badgeColor() ?? 'gray')
                    ->searchable(),

                TextColumn::make('employeeTypes.name')
                    ->label('Tipe Karyawan')
                    ->badge()
                    ->placeholder('Semua Tipe Karyawan'),

                TextColumn::make('calculation_type')
                    ->label('Metode Perhitungan')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'fixed' => 'Tetap',
                            'percentage' => 'Persentase',
                            'formula' => 'Formula',
                            default => ucfirst($state),
                        };
                    })
                    ->searchable(),

                TextColumn::make('component_value')
                    ->label('Nilai')
                    ->getStateUsing(function ($record) {
                        $method = $record->calculation_type;
                        $enum = CalculationTypeEnum::tryFrom($method);

                        return match ($enum) {
                            CalculationTypeEnum::FIXED =>
                                'IDR ' . number_format($record->default_value, 0, ',', '.'),

                            CalculationTypeEnum::PERCENTAGE =>
                                number_format($record->default_value, 0, ',', '.') . '%',

                            CalculationTypeEnum::FORMULA =>
                            'Formula',

                            default => '-',
                        };
                    }),

                TextColumn::make('assignments_count')
                    ->label('Dipakai Di')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn (int $state) => $state . ' assignment'),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->visible(fn (SalaryComponents $record) => $record->assignments_count === 0
                        && ! in_array(SalaryComponentCategoryEnum::tryFrom($record->category), SalaryComponentCategoryEnum::generalCategories(), true)),
            ]);
    }
}
