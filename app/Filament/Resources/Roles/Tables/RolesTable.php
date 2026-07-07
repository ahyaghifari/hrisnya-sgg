<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Role')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => collect(explode('_', $state))
                        ->map(fn ($word) => ucfirst($word))
                        ->implode(' ')
                    ),
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->default('Syifa Global Group')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Jumlah User')
                    ->counts('users')
                    ->badge()
                    ->color('info')
                    ->sortable(),
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
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Role $record) => $record->users()->count() === 0),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
