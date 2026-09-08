<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Models\Rekrutmen\Quiz;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
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
        return $this->jobOpeningId ? JobOpening::with('quizzes')->find($this->jobOpeningId) : null;
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
                return $record->quizAttempts()->where('status', 'submitted')->with(['quiz', 'answers'])->get()
                    ->map(function ($attempt) {
                        $correct = $attempt->answers->where('is_correct', true)->count();
                        $total = $attempt->answers->count();

                        return \Filament\Forms\Components\Placeholder::make('a_' . $attempt->id)
                            ->label($attempt->quiz->name)
                            ->content(new HtmlString(
                                '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">'
                                . '<span>' . $correct . ' dari ' . $total . ' benar'
                                . ($attempt->durationLabel() ? ' &middot; ' . $attempt->durationLabel() : '')
                                . ' &middot; ' . $attempt->submitted_at?->translatedFormat('d M Y, H:i') . '</span>'
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
                    ->with(['answers' => fn ($q) => $q->where('field_key', 'full_name'), 'quizAttempts'])
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
            ])
            ->recordActions([
                $this->lihatHasilAction(),
                $this->tandaiHasilTesAction(),
            ])
            ->toolbarActions([$this->kirimNotifBulkAction()])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat yang lolos seleksi berkas untuk lowongan ini muncul di sini.')
            ->emptyStateIcon('heroicon-o-computer-desktop');
    }
}
