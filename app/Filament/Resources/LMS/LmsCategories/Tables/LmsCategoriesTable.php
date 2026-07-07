<?php

namespace App\Filament\Resources\LMS\LmsCategories\Tables;

use App\Filament\Resources\LMS\LmsCategories\LmsCategoriesResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LmsCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => LmsCategoriesResource::canEdit($record)),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make()->visible(fn ($record) => LmsCategoriesResource::canDelete($record)),
                // ]),
            ]);
    }
}
