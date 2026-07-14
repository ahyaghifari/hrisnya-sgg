<?php

namespace App\Filament\Resources\LMS\Quizzes;

use App\Filament\Resources\LMS\Quizzes\Pages\EditQuiz;
use App\Filament\Resources\LMS\Quizzes\Pages\ListQuizzes;
use App\Filament\Resources\LMS\Quizzes\Pages\ViewQuiz;
use App\Filament\Resources\LMS\Quizzes\Schemas\QuizForm;
use App\Filament\Resources\LMS\Quizzes\Schemas\QuizInfolist;
use App\Filament\Resources\LMS\Quizzes\Tables\QuizzesTable;
use App\Models\LMS\LmsQuizzes;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class QuizResource extends Resource
{
    protected static ?string $model = LmsQuizzes::class;

    protected static ?string $slug = 'lms/quizzes';

    protected static ?string $navigationLabel = "Kuis";

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'Quiz';

    public static function form(Schema $schema): Schema
    {
        return QuizForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuizzesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizzes::route('/'),
            // 'view' => ViewQuiz::route('/{record}'),
            // 'create' => CreateQuiz::route('/create'),
            'edit' => EditQuiz::route('/{record}/edit'),
        ];
    }
}
