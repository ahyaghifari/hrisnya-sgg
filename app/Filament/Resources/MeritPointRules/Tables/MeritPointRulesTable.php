<?php

namespace App\Filament\Resources\MeritPointRules\Tables;

use App\Enum\MeritPoint\MeritPointType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MeritPointRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Nama Aturan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),

                TextColumn::make('points')
                    ->label('Poin')
                    ->sortable()
                    ->color(fn (int $state) => $state >= 0 ? 'success' : 'danger'),

                TextColumn::make('trigger_type')
                    ->label('Cara Pemakaian'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options(collect(MeritPointType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()])),

                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
