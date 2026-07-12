<?php

namespace App\Filament\Resources\SalaryComponentAssignments\Tables;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Pangkats;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalaryComponentAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'assignments as company_assignments_count' => fn ($q) => $q->where('salaryable_type', Companies::class),
                'assignments as pangkat_assignments_count' => fn ($q) => $q->where('salaryable_type', Pangkats::class),
                'assignments as jabatan_assignments_count' => fn ($q) => $q->where('salaryable_type', JobPositions::class),
            ]))
            ->columns([
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->placeholder('Semua Perusahaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Komponen')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company_assignments_count')
                    ->label('Jumlah ke Company')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray'),

                TextColumn::make('pangkat_assignments_count')
                    ->label('Jumlah ke Pangkat')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray'),

                TextColumn::make('jabatan_assignments_count')
                    ->label('Jumlah ke Jabatan')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
