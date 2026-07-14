<?php

namespace App\Filament\Pages\Lms;

use App\Models\LMS\LmsQuizUserResults;
use App\Repositories\LMS\QuizRepository;
use App\Services\LMS\QuizService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ReviewEssayAnswers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Review Jawaban Esai';

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.lms.review-essay-answers';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => LmsQuizUserResults::query()
                ->with(['user', 'quiz', 'meetingQuiz.meeting.course'])
                ->where('status', 'pending_review'))
            ->columns([
                TextColumn::make('user.name')->label('Peserta')->searchable(),
                TextColumn::make('quiz.title')->label('Quiz'),
                TextColumn::make('meetingQuiz.meeting.course.title')->label('Kursus'),
                TextColumn::make('completed_at')->label('Disubmit')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Nilai Esai')
                    ->modalHeading('Nilai Jawaban Esai')
                    ->schema(function (LmsQuizUserResults $record) {
                        $answers = app(QuizRepository::class)
                            ->getEssayAnswers($record->meeting_quiz_id, $record->user_id);

                        $fields = [];
                        foreach ($answers as $answer) {
                            $fields[] = Placeholder::make("question_{$answer->id}")
                                ->label($answer->question->question)
                                ->content($answer->user_answer ?: '(tidak dijawab)');
                            $fields[] = TextInput::make("scores.{$answer->id}")
                                ->label('Skor (maks ' . $answer->question->max_score . ')')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue($answer->question->max_score)
                                ->default(0)
                                ->required();
                        }

                        return $fields;
                    })
                    ->action(function (array $data, LmsQuizUserResults $record): void {
                        app(QuizService::class)->reviewEssayAnswers(
                            $record->meeting_quiz_id,
                            $record->user_id,
                            $data['scores'] ?? [],
                            Auth::id(),
                        );

                        Notification::make()->title('Jawaban esai berhasil dinilai')->success()->send();
                    }),
            ])
            ->defaultSort('completed_at', 'asc');
    }
}
