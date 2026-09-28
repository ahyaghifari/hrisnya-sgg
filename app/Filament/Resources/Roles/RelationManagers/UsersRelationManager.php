<?php

namespace App\Filament\Resources\Roles\RelationManagers;

use App\Enum\OrgStructurLevel;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Daftar User';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable(),
                TextColumn::make('profile.jobPosition.name')
                    ->label('Jabatan'),
                TextColumn::make('profile.jobPosition.division.name')
                    ->label('Divisi'),
            ])
            ->filters([
                SelectFilter::make('job_position_id')
                    ->label('Jabatan')
                    ->options(fn () => JobPositions::query()->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $value) => $q->whereHas('profile', fn ($q) => $q->where('job_position_id', $value)),
                    )),

                SelectFilter::make('division_id')
                    ->label('Divisi')
                    ->options(fn () => OrgStructure::query()->where('level', OrgStructurLevel::LEVEL_DIVISION->value)->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $value) => $q->whereHas(
                            'profile.jobPosition.orgStructure',
                            fn ($q) => $q->where('id', $value)->orWhere('parent_id', $value),
                        ),
                    )),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Tambah User')
                    ->recordSelectSearchColumns(['name', 'nip'])
                    ->recordSelectOptionsQuery(fn ($query) => $query->when(
                        $this->getOwnerRecord()->company_id,
                        fn ($q, $companyId) => $q->where('company_id', $companyId),
                    )),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
