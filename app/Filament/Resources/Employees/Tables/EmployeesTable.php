<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Helpers\PermissionHelper;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile_picture')
                    ->label('')
                    ->disk('public')
                    ->getStateUsing(fn ($record) => $record->profilePhotoPath())
                    ->circular()
                    ->size(40),
                TextColumn::make('name')
                    ->description(fn ($record) => $record->email)
                    ->label('Nama')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByName($direction))
                    ->searchable(query: fn (Builder $query, string $search) => $query->searchName($search)),
                TextColumn::make('nip')->label('NIP')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByNip($direction))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereNipLike($search)),
                TextColumn::make('profile.department.name')->label('Departemen')->alignCenter()->default('-'),
                TextColumn::make('profile.jobPosition.name')
                    ->description(fn ($record) => $record->getRoleNames()->join(', '))
                    ->label('Jabatan / Role')
                    ->default('-')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereCompany(
                        \App\Models\Master\Companies::query()->where('name', 'like', "%{$search}%")->pluck('id')->all()
                    ))
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->defaultSort(fn (Builder $query, string $direction) => $query->orderByName($direction))
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $companyId) => $q->whereCompany($companyId),
                    ))
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
            ]);
    }
}
