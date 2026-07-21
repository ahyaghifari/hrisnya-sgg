<?php

namespace App\Filament\Resources\LMS\LmsCategories\Tables;

use App\Filament\Resources\LMS\LmsCategories\LmsCategoriesResource;
use App\Models\LMS\LmsCategories;
use Filament\Actions\DeleteAction;
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

                TextColumn::make('training_category')
                    ->label('Jenis Pelatihan')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->placeholder('-'),

                TextColumn::make('courses_count')
                    ->label('Jumlah Pelatihan')
                    ->counts('courses')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => LmsCategoriesResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (LmsCategories $record) => LmsCategoriesResource::canDelete($record) && $record->courses_count === 0),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
