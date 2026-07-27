<?php

namespace App\Filament\Resources\Internship\InternshipPositions\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Master\JobPositions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InternshipPositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Posisi Magang')->searchable()->sortable(),
                TextColumn::make('actual_manpower')->label('Kuota')->sortable(),
                TextColumn::make('terisi')
                    ->label('Terisi')
                    ->getStateUsing(fn (JobPositions $record) => $record->userAssignments()->count()),
                TextColumn::make('company.name')->label('Perusahaan')->sortable(),
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
                EditAction::make()->slideOver()
                    ->modalWidth(Width::Medium)
                    ->modalHeading('Edit Posisi Magang'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
