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
                ImageColumn::make('profile.profile_picture')
                    ->label('')
                    ->disk('public')
                    ->getStateUsing(fn ($record) => $record->profile?->profile_picture[0] ?? null)
                    ->circular()
                    ->size(40),
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

                // Magang = jabatannya ditandai is_internship (lihat InternshipPositionResource,
                // grup nav Internship) - satu filter tunggal (bukan 2 checkbox terpisah) karena
                // "hanya karyawan" dan "hanya magang" 2 sisi dari kondisi yang sama. Default
                // "Hanya Karyawan" (bukan "Semua") - dan filter ini disembunyikan total kalau
                // company yang dilihat admin belum punya anak magang sama sekali, supaya tidak
                // menambah filter yang tidak relevan buat mayoritas company.
                TernaryFilter::make('is_intern')
                    ->label('Tipe Karyawan')
                    ->trueLabel('Hanya Anak Magang')
                    ->falseLabel('Hanya Karyawan (bukan magang)')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas(
                            'profile.jobPosition',
                            fn (Builder $q) => $q->where('is_internship', true)
                        ),
                        false: fn (Builder $query) => $query->whereDoesntHave(
                            'profile.jobPosition',
                            fn (Builder $q) => $q->where('is_internship', true)
                        ),
                    )
                    ->visible(function () {
                        $query = User::query()->whereHas('profile.jobPosition', fn (Builder $q) => $q->where('is_internship', true));

                        $user = Auth::user();
                        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
                            $query->where('company_id', $user->company_id);
                        }

                        return $query->exists();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => EmployeeResource::canEdit($record)),
            ]);
    }
}
