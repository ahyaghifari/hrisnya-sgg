<?php

namespace App\Filament\Resources\Competency\Competencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompetenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Kompetensi')->searchable()->sortable(),
                TextColumn::make('description')->label('Deskripsi')->limit(50)->wrap(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->slideOver()->modalWidth('md')->modalHeading('Edit Kompetensi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
