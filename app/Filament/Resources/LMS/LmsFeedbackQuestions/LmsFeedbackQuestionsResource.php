<?php

namespace App\Filament\Resources\LMS\LmsFeedbackQuestions;

use App\Filament\Resources\LMS\LmsFeedbackQuestions\Pages\ManageLmsFeedbackQuestions;
use App\Models\LMS\LmsFeedbackQuestion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LmsFeedbackQuestionsResource extends Resource
{
    protected static ?string $model = LmsFeedbackQuestion::class;

    protected static ?string $slug = 'lms/lms-feedback-questions';

    protected static ?string $navigationLabel = 'Pertanyaan Evaluasi';

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group_label')
                    ->label('Kelompok')
                    ->helperText('Pengelompokan tampilan, mis. Instruktur, Materi, Fasilitas.')
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('question')
                    ->label('Pertanyaan')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
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
                EditAction::make()
                    ->hiddenLabel()
                    ->visible(fn ($record) => static::canEdit($record)),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn (LmsFeedbackQuestion $record) => static::canDelete($record) && $record->answers_count === 0),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLmsFeedbackQuestions::route('/'),
        ];
    }
}
