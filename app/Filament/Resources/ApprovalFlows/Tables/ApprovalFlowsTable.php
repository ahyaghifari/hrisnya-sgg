<?php

namespace App\Filament\Resources\ApprovalFlows\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ApprovalFlowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Alur Persetujuan')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('module')
                    ->label('Modul')
                    ->formatStateUsing(fn ($state) => \App\Enum\Approval\ApprovalModuleEnum::tryFrom($state)?->label() ?? $state)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('flow_type')
                    ->label('Tipe Alur')
                    ->formatStateUsing(fn ($state) => $state === 'default' ? 'Default' : 'Spesifik')
                    ->alignCenter()
                    ->badge()
                    ->colors([
                        'primary' => fn ($state) => $state === 'default',
                        'warning' => fn ($state) => $state !== 'default',
                    ]),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Tidak Aktif')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state,
                        'danger' => fn ($state) => !$state,
                    ])
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'asc')
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
            ]);
    }
}
