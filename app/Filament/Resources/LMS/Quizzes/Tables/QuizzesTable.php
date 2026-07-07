<?php

namespace App\Filament\Resources\LMS\Quizzes\Tables;

use App\Filament\Resources\LMS\Quizzes\QuizResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuizzesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap(),
                TextColumn::make('category.name')->label('Kategori')->searchable()->wrap(),
                TextColumn::make('questions_count')
                    ->label('Jumlah Soal')
                    ->counts('questions')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->preload()
                    ->multiple()
                    ->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => QuizResource::canEdit($record)),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
