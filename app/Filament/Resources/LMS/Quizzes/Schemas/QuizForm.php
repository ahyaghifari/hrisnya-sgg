<?php

namespace App\Filament\Resources\LMS\Quizzes\Schemas;

use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\QuestionBanks;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class QuizForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Kuis')
                    ->description('Form untuk membuat atau mengedit kuis.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('category_id')
                            ->label('Kategori Soal')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Pilih kategori soal')
                            ->columnSpanFull()
                            ->createOptionForm([
                                Section::make('Form Kategori')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Kategori')
                                            ->required()
                                            ->maxLength(100),
                                    ])
                            ])
                            ->createOptionModalHeading('Tambah Kategori Baru'),
                            
                        TextInput::make('title')
                            ->label('Judul Kuis')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Hidden::make('created_by')
                            ->default(Auth::id())
                            ->dehydrated(),
                    ]),

                Section::make('Daftar Soal')
                    ->visible(fn (Get $get) => $get('id') !== null)
                    ->headerActions([
                        Action::make('questions')
                            ->slideOver()
                            ->label('Tambah Soal')
                            ->modalHeading('Tambah Soal ke Kuis')
                            ->modalWidth('md')
                            ->schema([
                                Select::make('category_id')
                                    ->label('Filter Kategori')
                                    ->options(LmsCategories::pluck('name', 'id'))
                                    ->searchable()
                                    ->columnSpanFull()
                                    ->reactive()
                                    ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                                        if (blank($state)) {
                                            $set('question_bank_id', []);
                                            return;
                                        }

                                        $quizId = $get('id');
                                        $quiz = $quizId ? LmsQuizzes::find($quizId) : null;

                                        $existingSelections = $quiz
                                            ? $quiz->pivotTable()
                                                ->whereHas('questionBank', fn ($query) => $query->where('category_id', $state))
                                                ->pluck('question_bank_id')
                                            : collect();

                                        $currentSelections = collect($get('question_bank_id') ?? []);

                                        $availableQuestionIds = QuestionBanks::query()
                                            ->where('category_id', $state)
                                            ->pluck('id');

                                        $set('question_bank_id', $existingSelections
                                            ->merge($currentSelections)
                                            ->intersect($availableQuestionIds)
                                            ->unique()
                                            ->values()
                                            ->all());
                                    }),

                                CheckboxList::make('question_bank_id')
                                    ->label('Soal')
                                    ->afterStateHydrated(function (CheckboxList $component, ?array $state) {
                                        $record = $component->getRecord();
                                        if (!($record instanceof LmsQuizzes) || !empty($state)) {
                                            return;
                                        }

                                        $component->state(
                                            $record->pivotTable()
                                                ->pluck('question_bank_id')
                                                ->filter(fn ($id) => filled($id))
                                                ->values()
                                                ->toArray()
                                        );
                                    })
                                    ->options(function (Get $get) {
                                        $categoryId = $get('category_id');
                                        if (!$categoryId) {
                                            return [];
                                        }

                                        return QuestionBanks::query()
                                            ->where('category_id', $categoryId)
                                            ->pluck('question', 'id')
                                            ->map(function ($question) {
                                                return strip_tags($question);
                                            })
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn (Get $get) => blank($get('category_id')))
                                    ->columnSpanFull(),
                            ])
                            ->action(function (array $data, callable $set, callable $get) {
                                $quizId = $get('id');
                                if (!$quizId) {
                                    return;
                                }

                                $quiz = LmsQuizzes::find($quizId);
                                if ($quiz) {
                                    $questionIds = collect($data['question_bank_id'] ?? [])
                                        ->filter(fn ($id) => filled($id))
                                        ->values()
                                        ->all();

                                    if (!empty($questionIds)) {
                                        $quiz->questions()->syncWithoutDetaching($questionIds);
                                    }

                                    $set('question_bank_id', []);
                                    
                                    $set('pivotTable', self::getPivotTableData($quiz));
                                }
                            })
                    ])
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('pivotTable')
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Repeater $component) {
                                $record = $component->getRecord();
                                if ($record instanceof LmsQuizzes) {
                                    $component->state(self::getPivotTableData($record));
                                }
                            })
                            ->hiddenLabel()
                            ->addable(false)
                            ->table([
                                TableColumn::make('Soal'),
                            ])
                            ->schema([
                                TextEntry::make('questionBank.question')
                                    ->hiddenLabel()
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                    ])
            ]);
    }

    protected static function getPivotTableData(LmsQuizzes $quiz): array
    {
        return $quiz
            ->pivotTable()
            ->with('questionBank')
            ->get()
            ->map(static function ($pivot) {
                return [
                    'id' => $pivot->id,
                    'quiz_id' => $pivot->quiz_id,
                    'question_bank_id' => $pivot->question_bank_id,
                    'questionBank' => [
                        'question' => $pivot->questionBank->question ?? '',
                    ],
                ];
            })
            ->toArray();
    }
}
