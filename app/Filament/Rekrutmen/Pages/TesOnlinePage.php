<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Models\Rekrutmen\OfflineTest;
use App\Models\Rekrutmen\OfflineTestResult;
use App\Models\Rekrutmen\OfflineTestSchedule;
use App\Models\Rekrutmen\Quiz;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

/**
 * Tes — sisi admin alur kuis. Port dari TesOnlinePage project `rekrutmen` (FL3 -> FL4),
 * DB `recruitment`. Pilih kuis per lowongan, aktifkan tes (set quiz_sent_at + email
 * QUIZ_SENT auto-kirim), catat keputusan lulus (email auto) / tidak lulus (draft, kirim
 * lewat aksi bulk "Kirim Email Pemberitahuan"). Pengerjaan kuis oleh kandidat tetap di
 * project `rekrutmen`.
 */
class TesOnlinePage extends Page implements HasActions, HasForms, HasTable
{
    use \App\Filament\Rekrutmen\Concerns\PipelineTabs;
    use \App\Filament\Rekrutmen\Concerns\ScopedToCompany;
    use \App\Filament\Rekrutmen\Concerns\SendsApplicantNotifications;
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.tes-online-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $navigationLabel = 'Tes';

    protected static ?string $title = 'Tes';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 1;

    #[Url]
    public ?string $scheduleTestId = null;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('View:JobOpenings');
    }

    private function canDecide(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('Update:JobOpenings');
    }

    public function updatedCompanyId(): void
    {
        $this->jobOpeningId = null;
        $this->resetTable();
    }

    public function updatedJobOpeningId(): void
    {
        $this->scheduleTestId = null;
        $this->resetTable();
    }

    public function getJobOpenings(): \Illuminate\Support\Collection
    {
        return $this->companyId
            ? JobOpening::where('company_id', $this->companyId)->latest()->get(['id', 'title'])
            : collect();
    }

    public function getSelectedJobOpening(): ?JobOpening
    {
        return $this->jobOpeningId ? JobOpening::with(['quizzes', 'offlineTests'])->find($this->jobOpeningId) : null;
    }

    public function getBelumDiaktifkanCount(): int
    {
        return $this->jobOpeningId
            ? JobApplication::where('job_opening_id', $this->jobOpeningId)->where('status', 'shortlisted')->whereNull('quiz_sent_at')->count()
            : 0;
    }

    public function hasQuizAssigned(): bool
    {
        return (bool) $this->getSelectedJobOpening()?->quizzes->count();
    }

    public function hasOfflineTestAssigned(): bool
    {
        return (bool) $this->getSelectedJobOpening()?->offlineTests->count();
    }

    public function getSchedulingOfflineTest(): ?OfflineTest
    {
        return $this->scheduleTestId
            ? $this->getSelectedJobOpening()?->offlineTests->firstWhere('id', $this->scheduleTestId)
            : null;
    }

    public function getOfflineTestDraft(): ?OfflineTestSchedule
    {
        return $this->jobOpeningId && $this->scheduleTestId
            ? OfflineTestSchedule::draftFor($this->jobOpeningId, $this->scheduleTestId)
            : null;
    }

    public function getPublishedOfflineTest(): ?OfflineTestSchedule
    {
        return $this->jobOpeningId && $this->scheduleTestId
            ? OfflineTestSchedule::publishedFor($this->jobOpeningId, $this->scheduleTestId)
            : null;
    }

    /** Kandidat lolos berkas yang bisa diundang ke sesi tes offline (sama seperti daftar tabel utama). */
    private function shortlistedOptions(): array
    {
        if (! $this->jobOpeningId) {
            return [];
        }

        return JobApplication::with(['answers' => fn ($q) => $q->where('field_key', 'full_name')])
            ->where('job_opening_id', $this->jobOpeningId)->where('status', 'shortlisted')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->answers->firstWhere('field_key', 'full_name')?->value ?? '(tanpa nama)'])
            ->all();
    }

    public function isQuizLocked(): bool
    {
        return $this->jobOpeningId
            && JobApplication::where('job_opening_id', $this->jobOpeningId)->whereNotNull('quiz_sent_at')->exists();
    }

    public function pilihKuisAction(): Action
    {
        return Action::make('pilihKuis')
            ->label($this->hasQuizAssigned() ? 'Ubah Kuis' : 'Pilih Kuis')
            ->icon('heroicon-m-clipboard-document-list')
            ->color('gray')
            ->visible(fn () => $this->canDecide() && (bool) $this->jobOpeningId)
            ->disabled(fn () => $this->isQuizLocked())
            ->tooltip(fn () => $this->isQuizLocked() ? 'Tes sudah diaktifkan — kuis tidak bisa diubah.' : null)
            ->schema([
                Select::make('quiz_ids')
                    ->label('Kuis')
                    ->options(fn () => Quiz::orderBy('name')->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->fillForm(fn () => ['quiz_ids' => $this->getSelectedJobOpening()?->quizzes->pluck('id')->all() ?? []])
            ->modalHeading('Pilih Kuis untuk Lowongan Ini')
            ->modalSubmitActionLabel('Simpan')
            ->action(function (array $data): void {
                $this->getSelectedJobOpening()?->quizzes()->sync($data['quiz_ids'] ?? []);
                Notification::make()->title('Kuis lowongan tersimpan')->success()->send();
            });
    }

    public function exportTesAction(): Action
    {
        return Action::make('exportTes')
            ->label('Export Hasil Tes')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('gray')
            ->visible(fn () => (bool) $this->jobOpeningId)
            ->schema([
                Select::make('quiz_id')
                    ->label('Kuis')
                    ->options(fn () => $this->getSelectedJobOpening()?->quizzes->pluck('name', 'id') ?? [])
                    ->required(),
            ])
            ->modalHeading('Export Hasil Tes')
            ->modalSubmitActionLabel('Unduh')
            ->action(function (array $data) {
                $filename = 'hasil-tes-' . \Illuminate\Support\Str::slug($this->getSelectedJobOpening()?->title ?? 'lowongan') . '-' . now()->format('Ymd') . '.xlsx';

                return Quiz::find($data['quiz_id'])?->type === 'psikotes'
                    ? (new \App\Exports\Rekrutmen\PsikotesResultExport($this->jobOpeningId, $data['quiz_id']))->download($filename)
                    : Excel::download(new \App\Exports\Rekrutmen\TesBidangResultExport($this->jobOpeningId, $data['quiz_id']), $filename);
            });
    }

    public function pilihTesOfflineAction(): Action
    {
        return Action::make('pilihTesOffline')
            ->label($this->hasOfflineTestAssigned() ? 'Ubah Tes Offline' : 'Pasang Tes Offline')
            ->icon('heroicon-m-clipboard-document-list')
            ->color('gray')
            ->visible(fn () => $this->canDecide() && (bool) $this->jobOpeningId)
            ->schema([
                Select::make('offline_test_ids')
                    ->label('Tes Offline')
                    ->options(fn () => OfflineTest::orderBy('name')->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan kalau lowongan ini tidak pakai tes offline.'),
            ])
            ->fillForm(fn () => ['offline_test_ids' => $this->getSelectedJobOpening()?->offlineTests->pluck('id')->all() ?? []])
            ->modalHeading('Pasang Tes Offline untuk Lowongan Ini')
            ->modalSubmitActionLabel('Simpan')
            ->action(function (array $data): void {
                $this->getSelectedJobOpening()?->offlineTests()->sync($data['offline_test_ids'] ?? []);
                Notification::make()->title('Tes offline lowongan tersimpan')->success()->send();
            });
    }

    public function catatTesOfflineAction(): Action
    {
        return Action::make('catatTesOffline')
            ->label('Catat Tes Offline')
            ->icon('heroicon-m-pencil-square')
            ->color('gray')
            ->visible(fn () => $this->canDecide() && $this->hasOfflineTestAssigned())
            ->schema(fn () => [
                Repeater::make('results')
                    ->label('Hasil per Tes Offline')
                    ->schema([
                        Select::make('offline_test_id')
                            ->label('Tes')
                            ->options(fn () => $this->getSelectedJobOpening()?->offlineTests->pluck('name', 'id') ?? [])
                            ->disabled()
                            ->dehydrated(),
                        \Filament\Forms\Components\TextInput::make('score')
                            ->label('Nilai')
                            ->numeric(),
                        Radio::make('result')->label('Keputusan')->options([
                            'lulus' => 'Lulus',
                            'tidak_lulus' => 'Tidak Lulus',
                        ]),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])
            ->fillForm(function ($record): array {
                $existing = $record->offlineTestResults->keyBy('offline_test_id');

                return [
                    'results' => $this->getSelectedJobOpening()?->offlineTests->map(fn ($test) => [
                        'offline_test_id' => $test->id,
                        'score' => $existing->get($test->id)?->score,
                        'result' => $existing->get($test->id)?->result,
                    ])->all() ?? [],
                ];
            })
            ->modalHeading('Catat Hasil Tes Offline')
            ->modalSubmitActionLabel('Simpan')
            ->action(function (array $data, $record): void {
                foreach ($data['results'] ?? [] as $row) {
                    OfflineTestResult::updateOrCreate(
                        ['job_application_id' => $record->id, 'offline_test_id' => $row['offline_test_id']],
                        ['score' => $row['score'], 'result' => $row['result'], 'recorded_at' => now()],
                    );
                }

                Notification::make()->title('Hasil tes offline tersimpan')->success()->send();
            });
    }

    public function jadwalTesOfflineAction(): Action
    {
        $options = $this->shortlistedOptions();
        $draft = $this->getOfflineTestDraft();

        return Action::make('jadwalTesOffline')
            ->label('Jadwalkan (' . count($options) . ' pelamar)')
            ->icon('heroicon-m-calendar-days')
            ->color('info')
            ->visible(fn () => $this->canDecide() && $this->scheduleTestId && count($options) > 0)
            ->fillForm([
                'test_date' => $draft?->test_date?->format('Y-m-d'),
                'test_time' => $draft?->test_time,
                'location' => $draft?->location,
                'notes' => $draft?->notes,
                'invited_ids' => $draft ? $draft->invitees()->pluck('job_application_id')->all() : [],
            ])
            ->schema([
                DatePicker::make('test_date')->label('Tanggal Tes')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('test_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi'),
                Textarea::make('notes')->label('Catatan untuk Pelamar')->rows(2),
                CheckboxList::make('invited_ids')
                    ->label('Pilih Pelamar yang Diundang')
                    ->helperText('Draft ini belum mengirim email apa pun.')
                    ->options($options)->searchable()->bulkToggleable()->columns(1),
            ])
            ->modalHeading('Jadwalkan Tes Offline')
            ->modalSubmitActionLabel('Simpan Draft Jadwal')
            ->action(function (array $data): void {
                if (! $this->jobOpeningId || ! $this->scheduleTestId) {
                    return;
                }
                OfflineTestSchedule::saveDraft($this->jobOpeningId, $this->scheduleTestId, $data, $data['invited_ids'] ?? []);
                Notification::make()->title('Draft jadwal tes offline tersimpan. Belum ada email terkirim.')->warning()->send();
            });
    }

    public function terapkanJadwalOfflineAction(): Action
    {
        $draft = $this->getOfflineTestDraft();
        $invitees = $draft ? $draft->invitees()->with(['jobApplication.answers'])->get() : collect();

        $namesHtml = $invitees->map(fn ($inv) => $inv->jobApplication->answers->firstWhere('field_key', 'full_name')?->value ?? '—')
            ->map(fn ($name) => '<li style="padding:6px 10px;border-bottom:1px solid #e5e7eb;font-size:13px;">' . e($name) . '</li>')
            ->join('');

        return Action::make('terapkanJadwalOffline')
            ->label('Terapkan Jadwal (' . $invitees->count() . ')')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn () => $this->canDecide() && $this->scheduleTestId && $draft !== null)
            ->modalHeading('Terapkan Jadwal Tes Offline?')
            ->modalDescription(fn () => 'Tanggal: ' . ($draft?->test_date?->translatedFormat('d F Y') ?? '-')
                . '  |  Waktu: ' . ($draft?->test_time ? substr($draft->test_time, 0, 5) : '-')
                . ($draft?->location ? '  |  ' . $draft->location : ''))
            ->schema([
                Placeholder::make('diundang')
                    ->label($invitees->count() . ' Pelamar Diundang')
                    ->content(new HtmlString('<ul style="border:1px solid #e5e7eb;border-radius:8px;max-height:200px;overflow-y:auto;margin:0;padding:0;list-style:none;">' . ($namesHtml ?: '<li style="padding:6px 10px;font-size:13px;color:#9ca3af;">Tidak ada</li>') . '</ul>')),
            ])
            ->modalSubmitActionLabel('Terapkan')
            ->action(function (): void {
                $draft = $this->getOfflineTestDraft();
                if (! $draft) {
                    return;
                }
                $draft->apply();
                Notification::make()->title('Jadwal diterapkan. Undangan tes offline otomatis dikirim ke email pelamar.')->success()->send();
            });
    }

    public function rescheduleSemuaOfflineAction(): Action
    {
        $published = $this->getPublishedOfflineTest();

        return Action::make('rescheduleSemuaOffline')
            ->label('Ubah Jadwal — Semua')
            ->icon('heroicon-m-calendar')
            ->color('warning')
            ->visible(fn () => $this->canDecide() && $this->scheduleTestId && $published !== null)
            ->fillForm([
                'test_date' => $published?->test_date?->format('Y-m-d'),
                'test_time' => $published?->test_time,
                'location' => $published?->location,
                'notes' => $published?->notes,
            ])
            ->schema([
                DatePicker::make('test_date')->label('Tanggal Tes Baru')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('test_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi'),
                Textarea::make('notes')->label('Catatan untuk Pelamar')->rows(2),
            ])
            ->modalHeading('Ubah Jadwal Tes Offline — Semua Peserta')
            ->modalDescription('Semua pelamar di jadwal terbit tes ini dipindah ke jadwal baru & dapat email pemberitahuan.')
            ->modalSubmitActionLabel('Simpan & Kirim Pemberitahuan')
            ->action(function (array $data): void {
                if (! $this->jobOpeningId || ! $this->scheduleTestId) {
                    return;
                }
                $n = OfflineTestSchedule::rescheduleFor($this->jobOpeningId, $this->scheduleTestId, $data);
                Notification::make()->title("Jadwal tes offline diperbarui. {$n} pelamar dinotifikasi via email.")->success()->send();
            });
    }

    public function rescheduleOfflineTesSatuAction(): Action
    {
        return Action::make('rescheduleOfflineTesSatu')
            ->label('Ubah Jadwal Tes Offline')
            ->icon('heroicon-m-calendar')
            ->color('warning')
            ->visible(fn ($record) => $this->canDecide() && $this->publishedInviteesFor($record)->isNotEmpty())
            ->schema(fn () => [
                Repeater::make('jadwal')
                    ->label('Jadwal per Tes')
                    ->schema([
                        Hidden::make('invitee_id'),
                        TextInput::make('nama_tes')->label('Tes')->disabled()->dehydrated(false),
                        DatePicker::make('test_date')->label('Tanggal Baru')->required()->native(false)->displayFormat('d/m/Y'),
                        TimePicker::make('test_time')->label('Waktu')->seconds(false),
                        TextInput::make('location')->label('Lokasi'),
                        Textarea::make('notes')->label('Catatan')->rows(1),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])
            ->fillForm(fn ($record) => [
                'jadwal' => $this->publishedInviteesFor($record)
                    ->map(fn ($inv) => [
                        'invitee_id' => $inv->id,
                        'nama_tes' => $inv->schedule?->offlineTest?->name,
                        'test_date' => $inv->schedule?->test_date?->format('Y-m-d'),
                        'test_time' => $inv->schedule?->test_time,
                        'location' => $inv->schedule?->location,
                        'notes' => $inv->schedule?->notes,
                    ])->values()->all(),
            ])
            ->modalHeading('Ubah Jadwal Tes Offline — Pelamar Ini')
            ->modalDescription('Hanya pelamar ini yang dipindah ke jadwal baru. Peserta lain tidak terpengaruh.')
            ->modalSubmitActionLabel('Simpan & Kirim Pemberitahuan')
            ->action(function (array $data, $record): void {
                $invitees = $record->offlineTestInvitees->keyBy('id');

                foreach ($data['jadwal'] ?? [] as $row) {
                    $invitee = $invitees->get($row['invitee_id'] ?? null);
                    if ($invitee) {
                        OfflineTestSchedule::rescheduleOne($invitee, $row);
                    }
                }

                Notification::make()->title('Jadwal tes offline pelamar ini diperbarui & email terkirim.')->success()->send();
            });
    }

    /** Undangan tes offline pelamar ini yang jadwalnya sudah diterapkan (bisa direschedule). */
    private function publishedInviteesFor(JobApplication $record): \Illuminate\Support\Collection
    {
        return $record->offlineTestInvitees->load('schedule.offlineTest')
            ->filter(fn ($inv) => $inv->schedule?->status === 'published');
    }

    public function aktifkanTesAction(): Action
    {
        $count = $this->getBelumDiaktifkanCount();

        return Action::make('aktifkanTes')
            ->label("Aktifkan Tes untuk Semua ({$count})")
            ->icon('heroicon-m-play')
            ->color('info')
            ->visible(fn () => $this->canDecide() && $this->hasQuizAssigned() && $count > 0)
            ->requiresConfirmation()
            ->modalHeading('Aktifkan Tes')
            ->modalDescription("Tes diaktifkan untuk {$count} kandidat lolos berkas yang belum diaktifkan. Email pemberitahuan dibuat sebagai draft (dikirim di tahap kirim notif).")
            ->modalSubmitActionLabel('Aktifkan')
            ->action(function (): void {
                if (! $this->jobOpeningId) {
                    return;
                }

                $apps = JobApplication::where('job_opening_id', $this->jobOpeningId)
                    ->where('status', 'shortlisted')->whereNull('quiz_sent_at')->get();

                foreach ($apps as $app) {
                    $app->update(['quiz_sent_at' => now()]);
                    ApplicationNotification::createAndSend($app, CompanyMessageTemplate::EVENT_QUIZ_SENT);
                }

                $this->resetTable();
                Notification::make()->title(count($apps) . ' kandidat diaktifkan tesnya (email terkirim).')->success()->send();
            });
    }

    public function tandaiHasilTesAction(): Action
    {
        return Action::make('tandaiHasilTes')
            ->label('Catat Keputusan')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('gray')
            ->visible(fn ($record) => $this->canDecide() && $record?->quiz_sent_at !== null && $record?->quiz_result === null)
            ->schema([
                Radio::make('hasil')->label('Keputusan')->options([
                    'lulus' => 'Lulus',
                    'tidak_lulus' => 'Tidak Lulus',
                ])->required(),
            ])
            ->action(function (array $data, $record): void {
                if ($data['hasil'] === 'lulus') {
                    $record->update(['quiz_result' => 'lulus']);
                    $record->logMilestone('quiz_lulus');
                    ApplicationNotification::createAndSend($record, CompanyMessageTemplate::EVENT_QUIZ_PASSED);
                } else {
                    $record->update(['quiz_result' => 'tidak_lulus', 'status' => 'rejected']);
                    ApplicationNotification::createDraftFor($record, CompanyMessageTemplate::EVENT_QUIZ_REJECTED);
                }

                Notification::make()->title('Keputusan tersimpan')->success()->send();
            });
    }

    public function lihatHasilAction(): Action
    {
        return Action::make('lihatHasil')
            ->label('Lihat Hasil')
            ->icon('heroicon-m-document-magnifying-glass')
            ->color('gray')
            ->visible(fn ($record) => $record->quizAttempts->where('status', 'submitted')->isNotEmpty())
            ->modalHeading('Hasil Tes')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema(function ($record) {
                return $record->quizAttempts()->where('status', 'submitted')->with(['quiz.questions.section', 'answers'])->get()
                    ->map(function ($attempt) {
                        // Total = semua soal kuis (baris answers hanya ada untuk soal yang dijawab).
                        $sections = $attempt->questionsBySection();
                        $correct = $sections->sum('correct');
                        $total = $sections->sum('total');
                        $perSubtest = $sections->count() > 1 || ($sections->first()['name'] ?? null)
                            ? '<ul style="margin:6px 0 0;padding-left:18px;font-size:.85em;">'
                                . $sections->map(fn ($s) => '<li>' . e($s['name'] ?? 'Tanpa subtest') . ': ' . $s['correct'] . ' benar dari ' . $s['total']
                                    . ' (' . $s['answered'] . ' dijawab)</li>')->implode('')
                                . '</ul>'
                            : '';

                        return \Filament\Forms\Components\Placeholder::make('a_' . $attempt->id)
                            ->label($attempt->quiz->name)
                            ->content(new HtmlString(
                                '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">'
                                . '<span>' . $correct . ' dari ' . $total . ' benar'
                                . ' &middot; skor ' . (float) $attempt->score . '/100'
                                . ($total > 0 ? ' (' . round(100 / $total, 2) . ' poin/soal)' : '')
                                . ($attempt->durationLabel() ? ' &middot; ' . $attempt->durationLabel() : '')
                                . ' &middot; ' . $attempt->submitted_at?->translatedFormat('d M Y, H:i') . $perSubtest . '</span>'
                                . '<a href="' . \App\Filament\Rekrutmen\Pages\HasilTesDetailPage::getUrl() . '?attempt=' . $attempt->id . '" style="font-weight:700;">Detail &rarr;</a>'
                                . '</div>'
                            ));
                    })->all();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with(['answers' => fn ($q) => $q->where('field_key', 'full_name'), 'quizAttempts', 'offlineTestResults', 'offlineTestInvitees.schedule.offlineTest'])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId)->where('status', 'shortlisted'),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                TextColumn::make('quiz_sent_at')->label('Diaktifkan')->dateTime('d M Y, H:i')->placeholder('Belum diaktifkan'),
                TextColumn::make('progress')->label('Progres Tes')->badge()
                    ->state(function ($record) {
                        $total = $this->getSelectedJobOpening()?->quizzes->count() ?? 0;
                        $done = $record->quizAttempts->where('status', 'submitted')->count();

                        return "{$done}/{$total} selesai";
                    }),
                TextColumn::make('score')->label('Skor Rata-rata')
                    ->state(function ($record) {
                        $scores = $record->quizAttempts->where('status', 'submitted')->pluck('score');

                        return $scores->isEmpty() ? '—' : round($scores->avg(), 2) . '%';
                    }),
                TextColumn::make('quiz_result')->label('Keputusan')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus', default => 'Belum dicatat',
                    })
                    ->color(fn ($state) => match ($state) {
                        'lulus' => 'success', 'tidak_lulus' => 'danger', default => 'gray',
                    }),
                TextColumn::make('offline_result')->label('Tes Offline')->badge()
                    ->visible(fn () => $this->hasOfflineTestAssigned())
                    ->state(function ($record) {
                        $total = $this->getSelectedJobOpening()?->offlineTests->count() ?? 0;
                        $done = $record->offlineTestResults->whereNotNull('result')->count();
                        $failed = $record->offlineTestResults->where('result', 'tidak_lulus')->isNotEmpty();

                        return $failed ? 'Tidak Lulus' : ($done >= $total && $total > 0 ? 'Lulus' : "{$done}/{$total} dicatat");
                    })
                    ->color(fn ($state) => match (true) {
                        $state === 'Lulus' => 'success',
                        $state === 'Tidak Lulus' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                $this->lihatHasilAction(),
                $this->tandaiHasilTesAction(),
                $this->catatTesOfflineAction(),
                $this->rescheduleOfflineTesSatuAction(),
            ])
            ->toolbarActions([$this->kirimNotifBulkAction()])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat yang lolos seleksi berkas untuk lowongan ini muncul di sini.')
            ->emptyStateIcon('heroicon-o-computer-desktop');
    }
}
