<?php

namespace App\Filament\Resources\Master\Divisions\Tables;

use App\Filament\Resources\Master\Divisions\DivisionsResource;
use App\Helpers\PermissionHelper;
use App\Models\Master\OrgStructure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DivisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Perusahaan')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Departemen')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama Divisi')->searchable()->sortable(),
                TextColumn::make('capacity')->label('Kapasitas')->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),

                SelectFilter::make('parent_id')
                    ->label('Departemen')
                    ->options(function () {
                        $user = Auth::user();

                        if (PermissionHelper::hasGlobalRole($user)) {
                            return OrgStructure::where('level', \App\Enum\OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                ->pluck('name', 'id');
                        } else {
                            return OrgStructure::where('level', \App\Enum\OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                ->where('company_id', $user->company_id)
                                ->pluck('name', 'id');
                        }
                    })
                    ->preload()
                    ->searchable()
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => DivisionsResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
