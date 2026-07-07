<?php

namespace App\Filament\Resources\ManPower\JobOpenings\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class JobOpeningsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobPosition.name')->label('Posisi Jabatan'),
                TextColumn::make('jobPosition.orgStructure.name')->label('Unit'),
                TextColumn::make('quota')->label('Kuota')->alignCenter(),
                TextColumn::make('applicants_count')->label('Jumlah Pelamar')
                    ->counts('applicants')
                    ->alignCenter(),
                TextColumn::make('is_active')->label('Status')
                    ->badge()
                    ->colors([
                        'success' => fn ($state): bool => $state,
                        'danger' => fn ($state): bool => ! $state,
                    ])
                    ->formatStateUsing(fn ($state): string => $state ? 'Aktif' : 'Tidak Aktif'),
            ])
            ->heading('Lowongan Kerja - ' . Auth::user()->company->name)
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
                ViewAction::make()->color('primary')->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
