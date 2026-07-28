<?php

namespace App\Filament\Resources\LMS\LmsFeedbackQuestions;

use App\Filament\Resources\LMS\LmsFeedbackQuestions\Pages\ListLmsFeedbackQuestions;
use App\Filament\Resources\LMS\LmsFeedbackQuestions\Schemas\LmsFeedbackQuestionsForm;
use App\Filament\Resources\LMS\LmsFeedbackQuestions\Tables\LmsFeedbackQuestionsTable;
use App\Models\LMS\LmsFeedbackQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LmsFeedbackQuestionsResource extends Resource
{
    protected static ?string $model = LmsFeedbackQuestion::class;

    protected static ?string $slug = 'lms/lms-feedback-questions';

    protected static ?string $navigationLabel = 'Pertanyaan Evaluasi';

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    public static function form(Schema $schema): Schema
    {
        return LmsFeedbackQuestionsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LmsFeedbackQuestionsTable::configure($table);
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
            'index' => ListLmsFeedbackQuestions::route('/'),
        ];
    }
}
