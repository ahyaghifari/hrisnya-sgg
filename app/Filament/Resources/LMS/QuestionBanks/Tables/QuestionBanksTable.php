<?php

namespace App\Filament\Resources\LMS\QuestionBanks\Tables;

use App\Enum\QuestionType;
use App\Filament\Resources\LMS\QuestionBanks\QuestionBanksResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class QuestionBanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Soal')
                    ->searchable()
                    ->wrap()
                    ->html()
                    ->formatStateUsing(fn ($state) => Str::limit(strip_tags($state), 150))
                    ->description(fn ($record) => "Tipe: " . QuestionType::from($record->type)->getLabel()),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->placeholder('Semua Kategori')
                    ->searchable()
                    ->preload()
                    ->multiple()
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => QuestionBanksResource::canEdit($record)),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make()->visible(fn ($record) => QuestionBanksResource::canDelete($record)),
                // ]),
            ]);
    }
}
