<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Helpers\PermissionHelper;
use App\Models\Master\OrgStructure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->description(fn ($record) => $record->email)
                    ->label('Nama')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nip')->label('NIP')->sortable()->searchable(),
                TextColumn::make('profile.department.name')->label('Departemen')->alignCenter()->default('-'),
                TextColumn::make('profile.jobPosition.name')
                    ->description(fn ($record) => $record->getRoleNames()->join(', '))
                    ->label('Jabatan / Role')
                    ->default('-')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
                    
                SelectFilter::make('department')
                    ->label('Departemen')
                    ->options(function () {
                        $companyId = PermissionHelper::hasGlobalRole(Auth::user()) ? null : Auth::user()->company_id;
                        if (!$companyId) {
                            return OrgStructure::where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                ->pluck('name', 'id')
                                ->toArray();
                        }

                        return OrgStructure::where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                            ->where('company_id', $companyId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => EmployeeResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
