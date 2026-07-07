<?php

namespace App\Filament\Resources\Master\TaskServices\Tables;

use App\Filament\Resources\Master\TaskServices\TaskServiceResource;
use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TaskServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Layanan')->sortable()->searchable()
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
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Edit Data Layanan')
                    ->visible(fn ($record) => TaskServiceResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn ($record) => TaskServiceResource::canDelete($record)),
            ])
            ->toolbarActions([
                //BulkActionGroup::make([
                    //DeleteBulkAction::make(),
                //]),
            ]);
    }
}
