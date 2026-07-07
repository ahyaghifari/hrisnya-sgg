<?php

namespace App\Filament\Resources\Master\SubDivisions\Tables;

use App\Filament\Resources\Master\SubDivisions\SubDivisionsResource;
use App\Helpers\PermissionHelper;
use App\Models\Master\OrgStructure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubDivisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Perusahaan')->sortable()->searchable(),
                TextColumn::make('name')->label('Nama Sub Divisi')->sortable()->searchable(),
                TextColumn::make('parent.name')->label('Divisi')->sortable()->searchable(),
                TextColumn::make('capacity')->label('Kapasitas')->alignCenter(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),

                \Filament\Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Divisi')
                    ->options(function () {
                        $user = Auth::user();
                        if (PermissionHelper::hasGlobalRole($user)) {
                            return OrgStructure::where('level', \App\Enum\OrgStructurLevel::LEVEL_DIVISION->value)
                                ->pluck('name', 'id');
                        } else {
                            return OrgStructure::where('level', \App\Enum\OrgStructurLevel::LEVEL_DIVISION->value)
                                ->where('company_id', $user->company_id)
                                ->pluck('name', 'id');
                        }
                    })
                    ->preload()
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => SubDivisionsResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
