<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\Quiz;
use App\Models\Rekrutmen\QuizQuestion;
use App\Models\Rekrutmen\QuizSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Soal Kuis — port dari QuizQuestionsPage project `rekrutmen` (FL3 -> FL4), DB `recruitment`.
 * Editor repeater (simpan = hapus semua lalu tulis ulang) + unduh template + import Excel.
 * Gambar soal & gambar pilihan diunggah langsung ke storage publik project rekrutmen
 * (disk `rekrutmen_public`), path relatifnya sama dengan yang dibaca app rekrutmen.
 */
class QuizQuestionsPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.quiz-questions-page';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $quizId = null;
    public string $mode = 'table';
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('Update:JobOpenings');
    }

    public function mount(): void
    {
        $this->quizId = request('quiz');
        $this->form->fill(['questions' => []]);
    }

    public function getQuizzes(): \Illuminate\Support\Collection
    {
        return Quiz::orderBy('name')->get(['id', 'name']);
    }

    public function getQuizName(): ?string
    {
        return $this->quizId ? Quiz::whereKey($this->quizId)->value('name') : null;
    }

    public function updatedQuizId(): void
    {
        $this->mode = 'table';
        $this->resetTable();
    }

    public function switchMode(string $mode): void
    {
        $this->mode = $mode;

        if ($mode === 'edit') {
            $this->loadQuestionsIntoForm();
        }
    }

    private function loadQuestionsIntoForm(): void
    {
        $questions = $this->quizId
            ? QuizQuestion::where('quiz_id', $this->quizId)->orderBy('sort_order')->get()
            : collect();

        $this->form->fill([
            'questions' => $questions->map(fn (QuizQuestion $q) => [
                'quiz_section_id' => $q->quiz_section_id,
                'question' => $q->question,
                'image_path' => $q->image_path,
                'max_select' => $q->max_select,
                'options' => collect($q->options ?? [])->map(fn ($opt) => [
                    'label' => $opt,
                    'image' => $q->option_images[$opt] ?? null,
                    'is_correct' => $q->max_select
                        ? in_array($opt, explode(',', (string) $q->correct_option), true)
                        : $opt === $q->correct_option,
                ])->all(),
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Repeater::make('questions')
                    ->label('Daftar Soal')
                    ->schema([
                        Select::make('quiz_section_id')
                            ->label('Subtest')
                            ->options(fn () => $this->quizId
                                ? QuizSection::where('quiz_id', $this->quizId)->orderBy('sort_order')->pluck('name', 'id')
                                : [])
                            ->placeholder('Tanpa subtest')
                            ->columnSpanFull(),
                        Textarea::make('question')->label('Pertanyaan')->required()->rows(2)->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Gambar Soal (opsional)')
                            ->image()
                            ->disk('rekrutmen_public')
                            ->directory('quiz-questions')
                            ->imagePreviewHeight('160')
                            ->nullable()
                            ->columnSpanFull(),
                        TextInput::make('max_select')
                            ->label('Maks. pilihan dipilih (kosong = pilih satu)')
                            ->numeric()
                            ->minValue(2)
                            ->nullable()
                            ->helperText('Isi untuk soal multi-jawaban, lalu centang semua jawaban benar.'),
                        Repeater::make('options')
                            ->label('Pilihan Jawaban')
                            ->schema([
                                TextInput::make('label')->label('Pilihan')->required()->columnSpan(2),
                                Checkbox::make('is_correct')->label('Jawaban Benar'),
                                FileUpload::make('image')
                                    ->label('Gambar Pilihan (opsional)')
                                    ->image()
                                    ->disk('rekrutmen_public')
                                    ->directory('quiz-options')
                                    ->imagePreviewHeight('80')
                                    ->nullable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->minItems(2)
                            ->defaultItems(2)
                            ->addActionLabel('Tambah Pilihan')
                            ->columnSpanFull(),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? 'Soal baru')
                    ->collapsible()
                    ->collapsed()
                    ->reorderable()
                    ->addActionLabel('Tambah Soal')
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        if (! $this->quizId) {
            return;
        }

        $state = $this->form->getState();

        QuizQuestion::where('quiz_id', $this->quizId)->delete();

        foreach ($state['questions'] ?? [] as $i => $q) {
            $options = collect($q['options'] ?? []);
            QuizQuestion::create([
                'quiz_id' => $this->quizId,
                'quiz_section_id' => $q['quiz_section_id'] ?? null,
                'question' => $q['question'],
                'image_path' => $q['image_path'] ?? null,
                'options' => $options->pluck('label')->all(),
                'option_images' => $options->filter(fn ($o) => ! empty($o['image']))->pluck('image', 'label')->all() ?: null,
                'max_select' => $q['max_select'] ?: null,
                'correct_option' => ($q['max_select'] ?? null)
                    ? $options->where('is_correct', true)->pluck('label')->implode(',')
                    : ($options->firstWhere('is_correct', true)['label'] ?? null),
                'sort_order' => $i,
            ]);
        }

        Notification::make()->title('Soal tersimpan')->success()->send();
        $this->mode = 'table';
        $this->resetTable();
    }

    public function templateAction(): Action
    {
        return Action::make('template')
            ->label('Unduh Template')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('gray')
            ->visible(fn () => (bool) $this->quizId)
            ->action(fn () => Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromArray {
                public function array(): array
                {
                    return [
                        ['Pertanyaan', 'A', 'B', 'C', 'D', 'E', 'Jawaban Benar'],
                        ['Contoh: 2 + 2 = ?', '3', '4', '5', '6', '', 'B'],
                    ];
                }
            }, 'template-soal.xlsx'));
    }

    public function importAction(): Action
    {
        return Action::make('import')
            ->label('Import Soal (Excel)')
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            ->visible(fn () => (bool) $this->quizId)
            ->schema([
                FileUpload::make('file')
                    ->label('File Excel')
                    ->disk('local')
                    ->directory('quiz-imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->required()
                    ->helperText('Kolom: Pertanyaan, A, B, C, D, E, Jawaban Benar (isi hurufnya). Baris pertama header. Soal baru ditambahkan, lama tidak dihapus.'),
            ])
            ->modalHeading('Import Soal dari Excel')
            ->modalSubmitActionLabel('Import')
            ->action(function (array $data): void {
                $path = $data['file'];
                $rows = Excel::toArray(new class {}, Storage::disk('local')->path($path))[0] ?? [];
                Storage::disk('local')->delete($path);

                $header = array_map('trim', array_map('strtolower', array_shift($rows) ?? []));
                $nextSort = (int) (QuizQuestion::where('quiz_id', $this->quizId)->max('sort_order') ?? -1) + 1;

                $imported = 0;
                $skipped = 0;

                foreach ($rows as $row) {
                    if (empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                        continue;
                    }

                    $assoc = array_combine($header, array_pad($row, count($header), null));
                    $question = trim((string) ($assoc['pertanyaan'] ?? ''));

                    $optionsByLetter = [];
                    foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                        $val = trim((string) ($assoc[$letter] ?? ''));
                        if ($val !== '') {
                            $optionsByLetter[$letter] = $val;
                        }
                    }

                    $correct = $optionsByLetter[strtolower(trim((string) ($assoc['jawaban benar'] ?? '')))] ?? null;

                    if ($question === '' || count($optionsByLetter) < 2 || $correct === null) {
                        $skipped++;
                        continue;
                    }

                    QuizQuestion::create([
                        'quiz_id' => $this->quizId,
                        'question' => $question,
                        'options' => array_values($optionsByLetter),
                        'correct_option' => $correct,
                        'sort_order' => $nextSort++,
                    ]);
                    $imported++;
                }

                $this->resetTable();
                Notification::make()
                    ->title("{$imported} soal diimport" . ($skipped > 0 ? ", {$skipped} baris dilewati (tak lengkap)." : '.'))
                    ->color($skipped > 0 ? 'warning' : 'success')
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->quizId
                    ? QuizQuestion::query()->with('section')->where('quiz_id', $this->quizId)
                    : QuizQuestion::query()->whereRaw('1 = 0')
            )
            ->columns([
                TextColumn::make('sort_order')->label('#')->width(50)->sortable(),
                \Filament\Tables\Columns\ImageColumn::make('image_path')->label('Gambar')->disk('rekrutmen_public')->height(40),
                TextColumn::make('section.name')->label('Subtest')->placeholder('—'),
                TextColumn::make('question')->label('Pertanyaan')->limit(70)->wrap(),
                TextColumn::make('correct_option')->label('Jawaban Benar')->badge()->color('success'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                $this->templateAction(),
                $this->importAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada soal')
            ->emptyStateDescription($this->quizId ? 'Klik "Kelola Soal" untuk mulai.' : 'Pilih kuis dulu.')
            ->emptyStateIcon('heroicon-o-question-mark-circle');
    }
}
