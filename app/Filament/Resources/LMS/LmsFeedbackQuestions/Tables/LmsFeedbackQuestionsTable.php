<?php

namespace App\Filament\Resources\LMS\LmsFeedbackQuestions\Tables;

use App\Filament\Resources\LMS\LmsFeedbackQuestions\LmsFeedbackQuestionsResource;
use App\Models\LMS\LmsFeedbackQuestion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LmsFeedbackQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('answers'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('group_label')
                    ->label('Kelompok')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('question')
                    ->label('Pertanyaan')
                    ->wrap()
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('answers_count')
                    ->label('Jumlah Jawaban')
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => LmsFeedbackQuestionsResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (LmsFeedbackQuestion $record) => LmsFeedbackQuestionsResource::canDelete($record) && $record->answers_count === 0),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
