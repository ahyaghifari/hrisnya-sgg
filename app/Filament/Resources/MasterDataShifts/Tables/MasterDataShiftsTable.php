<?php

namespace App\Filament\Resources\MasterDataShifts\Tables;

use App\Filament\Resources\MasterDataShifts\MasterDataShiftResource;
use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MasterDataShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Shift')
                    ->description(fn ($record) => $record->code ? "Kode : {$record->code}" : null)
                    ->searchable()->sortable(),
                TextColumn::make('orgStructure.name')->label('Divisi')->searchable()->sortable(),
                TextColumn::make('start_time')->label('Waktu Mulai')->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i')),
                TextColumn::make('end_time')->label('Waktu Selesai')->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i')),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
                SelectFilter::make('org_structure_id')
                    ->label('Filter Divisi')
                    ->options(function () {
                        $companyId = Auth::user()->company_id;
                        return \App\Models\Master\OrgStructure::where('company_id', $companyId)
                            ->where('level', \App\Enum\OrgStructurLevel::LEVEL_DIVISION->value)
                            ->pluck('name', 'id');
                    })
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn($record) => MasterDataShiftResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
