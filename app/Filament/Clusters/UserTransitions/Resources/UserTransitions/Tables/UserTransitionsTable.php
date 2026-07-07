<?php

namespace App\Filament\Clusters\UserTransitions\Resources\UserTransitions\Tables;

use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserTransitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('transition_type')
                    ->label('Jenis Perubahan')
                    ->badge()
                    ->formatStateUsing(fn ($state) => UserTransitionTypeEnum::from($state)->label())
                    ->color(fn ($state) => UserTransitionTypeEnum::from($state)->color())
                    ->icon(fn ($state) => UserTransitionTypeEnum::from($state)->icon())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => UserTransitionStatusEnum::from($state)->getLabel())
                    ->color(fn ($state) => UserTransitionStatusEnum::from($state)->getColor())
                    ->icon(fn ($state) => UserTransitionStatusEnum::from($state)->getIcon())
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
