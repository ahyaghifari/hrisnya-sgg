<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\InterviewSchedule;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Models\Rekrutmen\JobOpeningStageLog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use UnitEnum;

/**
 * Wawancara — port dari WawancaraPage project `rekrutmen` (FL3 -> FL4), DB `recruitment`.
 * Jadwal draft -> terapkan (status `interview` + email undangan auto-kirim), catat hasil
 * per kandidat (lulus -> `offering`; tidak lulus -> `rejected` + draft notif, kirim lewat
 * aksi bulk "Kirim Email Pemberitahuan").
 */
class WawancaraPage extends Page implements HasActions, HasForms, HasTable
{
    use \App\Filament\Rekrutmen\Concerns\PipelineTabs;
    use \App\Filament\Rekrutmen\Concerns\ScopedToCompany;
    use \App\Filament\Rekrutmen\Concerns\SendsApplicantNotifications;
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.wawancara-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Wawancara';

    protected static ?string $title = 'Wawancara';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 2;


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

    /** Tahap wawancara yang sedang dibuka di halaman ini (1..interview_rounds lowongannya). */
    public int $activeRound = 1;

    public function updatedCompanyId(): void
    {
        $this->jobOpeningId = null;
        $this->activeRound = 1;
        $this->resetTable();
    }

    public function updatedJobOpeningId(): void
    {
        $this->activeRound = 1;
        $this->resetTable();
    }

    public function getJobOpenings(): \Illuminate\Support\Collection
    {
        return $this->companyId
            ? JobOpening::where('company_id', $this->companyId)->latest()->get(['id', 'title'])
            : collect();
    }

    /**
     * Jumlah tahap wawancara lowongan ini - diatur per lowongan, tidak dibatasi 2. Lowongan lama
     * tanpa setelan dianggap 1 tahap.
     */
    public function getInterviewRounds(): int
    {
        return $this->jobOpeningId
            ? max(1, JobOpening::find($this->jobOpeningId)?->interview_rounds ?: 1)
            : 1;
    }

    /** Tahap yang sedang dikerjakan di halaman ini, dijaga tetap di dalam rentang lowongannya. */
    public function currentRound(): int
    {
        return min(max(1, $this->activeRound), $this->getInterviewRounds());
    }

    public function updatedActiveRound(): void
    {
        $this->resetTable();
    }

    /** Nama tahap: nama dari jadwalnya kalau HR sudah mengisi, kalau tidak "Wawancara Tahap N". */
    public function roundLabel(int $round): string
    {
        $schedule = $this->getInterviewDraft($round) ?? $this->getPublishedInterview($round);

        return $schedule?->label() ?? 'Wawancara Tahap ' . $round;
    }

    public function getReadyCount(?int $round = null): int
    {
        return count($this->readyOptions($round ?? $this->currentRound()));
    }

    public function getInterviewDraft(?int $round = null): ?InterviewSchedule
    {
        return $this->jobOpeningId
            ? InterviewSchedule::draftForOpening($this->jobOpeningId, $round ?? $this->currentRound())
            : null;
    }

    public function getPublishedInterview(?int $round = null): ?InterviewSchedule
    {
        return $this->jobOpeningId
            ? InterviewSchedule::publishedForOpening($this->jobOpeningId, $round ?? $this->currentRound())
            : null;
    }

    /**
     * Pelamar yang siap diundang ke tahap ini.
     *
     * Tahap 1: lolos seleksi berkas & kuis, seperti sebelumnya. Tahap berikutnya: sudah LULUS tahap
     * sebelumnya dan belum pernah dijadwalkan di tahap ini - jadi satu orang tidak bisa diundang dua
     * kali ke tahap yang sama, dan yang tidak lulus tidak ikut terbawa.
     */
    private function readyOptions(int $round = 1): array
    {
        if (! $this->jobOpeningId || $round > $this->getInterviewRounds()) {
            return [];
        }

        $query = JobApplication::with(['answers' => fn ($q) => $q->where('field_key', 'full_name')])
            ->where('job_opening_id', $this->jobOpeningId);

        if ($round === 1) {
            $query->where('status', 'shortlisted')->where('quiz_result', 'lulus');
        } else {
            $query->where('status', 'interview')->with('interviewInvitees.schedule');
        }

        return $query->get()
            ->when($round > 1, fn ($apps) => $apps->filter(
                fn (JobApplication $a) => $a->interviewInviteeForRound($round - 1)?->result === 'lulus'
                    && $a->interviewInviteeForRound($round) === null
            ))
            ->mapWithKeys(fn ($a) => [$a->id => $a->answers->firstWhere('field_key', 'full_name')?->value ?? '(tanpa nama)'])
            ->all();
    }

    public function jadwalWawancaraAction(): Action
    {
        $round = $this->currentRound();
        $options = $this->readyOptions($round);
        $draft = $this->getInterviewDraft($round);
        $tahap = $this->roundLabel($round);

        return Action::make('jadwalWawancara')
            ->label('Jadwalkan ' . $tahap . ' (' . count($options) . ' pelamar)')
            ->icon('heroicon-m-calendar-days')
            ->color('info')
            ->visible(fn () => $this->canDecide() && count($options) > 0)
            ->fillForm([
                'name' => $draft?->name,
                'interview_date' => $draft?->interview_date?->format('Y-m-d'),
                'interview_time' => $draft?->interview_time,
                'location' => $draft?->location,
                'notes' => $draft?->notes,
                'invited_ids' => $draft ? $draft->invitees()->pluck('job_application_id')->all() : [],
            ])
            ->schema([
                TextInput::make('name')
                    ->label('Nama Tahap')
                    ->maxLength(255)
                    ->placeholder('Wawancara Tahap ' . $round)
                    ->helperText('Mis. "Wawancara HR" atau "Wawancara User". Kosongkan untuk memakai "Wawancara Tahap ' . $round . '".'),
                DatePicker::make('interview_date')->label('Tanggal Wawancara')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('interview_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi / Link Meeting'),
                Textarea::make('notes')->label('Catatan untuk Pelamar')->rows(2),
                CheckboxList::make('invited_ids')
                    ->label('Pilih Pelamar yang Diundang')
                    ->helperText('Draft ini belum mengubah status pelamar.')
                    ->options($options)->searchable()->bulkToggleable()->columns(1),
            ])
            ->modalHeading('Jadwalkan ' . $tahap)
            ->modalSubmitActionLabel('Simpan Draft Jadwal')
            ->action(function (array $data) use ($round): void {
                if (! $this->jobOpeningId) {
                    return;
                }
                InterviewSchedule::saveDraft($this->jobOpeningId, $data, $data['invited_ids'] ?? [], $round);
                Notification::make()->title('Draft jadwal tersimpan. Status pelamar belum berubah.')->warning()->send();
            });
    }

    public function rescheduleWawancaraAction(): Action
    {
        $round = $this->currentRound();
        $published = $this->getPublishedInterview($round);

        return Action::make('rescheduleWawancara')
            ->label('Ubah Jadwal ' . $this->roundLabel($round) . ' — Semua')
            ->icon('heroicon-m-calendar')
            ->color('warning')
            ->visible(fn () => $this->canDecide() && $published !== null)
            ->fillForm([
                'interview_date' => $published?->interview_date?->format('Y-m-d'),
                'interview_time' => $published?->interview_time,
                'location' => $published?->location,
                'notes' => $published?->notes,
            ])
            ->schema([
                DatePicker::make('interview_date')->label('Tanggal Wawancara Baru')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('interview_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi / Link Meeting'),
                Textarea::make('notes')->label('Catatan untuk Pelamar')->rows(2),
            ])
            ->modalHeading('Ubah Jadwal ' . $this->roundLabel($round) . ' — Semua Peserta')
            ->modalDescription('Semua peserta tahap ini dipindah ke jadwal baru & dapat email pemberitahuan. Tahap lain tidak terpengaruh.')
            ->modalSubmitActionLabel('Simpan & Kirim Pemberitahuan')
            ->action(function (array $data) use ($round): void {
                if (! $this->jobOpeningId) {
                    return;
                }
                $n = InterviewSchedule::rescheduleOpening($this->jobOpeningId, $data, $round);
                $this->resetTable();
                Notification::make()->title("Jadwal diperbarui. {$n} pelamar dinotifikasi via email.")->success()->send();
            });
    }

    public function rescheduleOneWawancaraAction(): Action
    {
        return Action::make('rescheduleOneWawancara')
            ->label('Ubah Jadwal')
            ->icon('heroicon-m-calendar')
            ->color('warning')
            ->visible(fn ($record) => $this->canDecide() && $record?->interviewInvitee && $record->interview_result === null)
            ->fillForm(fn ($record) => [
                'interview_date' => $record->interviewInvitee?->schedule?->interview_date?->format('Y-m-d'),
                'interview_time' => $record->interviewInvitee?->schedule?->interview_time,
                'location' => $record->interviewInvitee?->schedule?->location,
                'notes' => $record->interviewInvitee?->schedule?->notes,
            ])
            ->schema([
                DatePicker::make('interview_date')->label('Tanggal Wawancara Baru')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('interview_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi / Link Meeting'),
                Textarea::make('notes')->label('Catatan untuk Pelamar')->rows(2),
            ])
            ->modalHeading('Ubah Jadwal Wawancara — Pelamar Ini')
            ->modalDescription('Hanya pelamar ini yang dipindah ke jadwal baru. Peserta lain tidak terpengaruh.')
            ->modalSubmitActionLabel('Simpan & Kirim Pemberitahuan')
            ->action(function (array $data, $record): void {
                if (! $record?->interviewInvitee) {
                    return;
                }
                InterviewSchedule::rescheduleOne($record->interviewInvitee, $data);
                $this->resetTable();
                Notification::make()->title('Jadwal wawancara pelamar ini diperbarui & email terkirim.')->success()->send();
            });
    }

    public function publishInterviewAction(): Action
    {
        $round = $this->currentRound();
        $draft = $this->getInterviewDraft($round);
        $invitees = $draft ? $draft->invitees()->with(['jobApplication.answers'])->get() : collect();
        $tahap = $this->roundLabel($round);

        $namesHtml = $invitees->map(fn ($inv) => $inv->jobApplication->answers->firstWhere('field_key', 'full_name')?->value ?? '—')
            ->map(fn ($name) => '<li style="padding:6px 10px;border-bottom:1px solid #e5e7eb;font-size:13px;">' . e($name) . '</li>')
            ->join('');

        return Action::make('publishInterview')
            ->label('Terapkan ' . $tahap . ' (' . $invitees->count() . ')')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn () => $this->canDecide() && $draft !== null)
            ->modalHeading('Terapkan Jadwal ' . $tahap . '?')
            ->modalDescription(fn () => 'Tanggal: ' . ($draft?->interview_date?->translatedFormat('d F Y') ?? '-')
                . '  |  Waktu: ' . ($draft?->interview_time ? substr($draft->interview_time, 0, 5) : '-')
                . ($draft?->location ? '  |  ' . $draft->location : ''))
            ->schema([
                Placeholder::make('diundang')
                    ->label($invitees->count() . ' Pelamar Diundang')
                    ->content(new HtmlString('<ul style="border:1px solid #e5e7eb;border-radius:8px;max-height:200px;overflow-y:auto;margin:0;padding:0;list-style:none;">' . ($namesHtml ?: '<li style="padding:6px 10px;font-size:13px;color:#9ca3af;">Tidak ada</li>') . '</ul>')),
            ])
            ->modalSubmitActionLabel('Terapkan')
            ->action(function () use ($round): void {
                $draft = $this->getInterviewDraft($round);
                if (! $draft) {
                    return;
                }
                [$count] = $draft->apply();
                // Log tahap pipeline cuma untuk tahap 1 - tahap 2 tidak memindahkan pelamar antar
                // status (dia sudah & tetap di `interview`), jadi tidak ada perpindahan buat dicatat.
                if ($round === 1 && $this->jobOpeningId) {
                    JobOpeningStageLog::record($this->jobOpeningId, 'shortlisted', 'interview', $count);
                }
                $this->resetTable();
                Notification::make()->title('Jadwal diterapkan. Undangan otomatis dikirim ke email pelamar.')->success()->send();
            });
    }

    public function tandaiHasilWawancaraAction(): Action
    {
        return Action::make('tandaiHasilWawancara')
            ->label('Catat Hasil')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('gray')
            // Yang dicatat hasil TAHAP yang sedang menunggu keputusan untuk orang itu. Kalau semua
            // tahapnya sudah diputuskan, tombolnya hilang.
            ->visible(fn ($record) => $this->canDecide() && $record?->pendingInterviewRound() !== null)
            ->modalHeading(fn ($record) => 'Catat Hasil ' . $this->roundLabel($record?->pendingInterviewRound() ?? 1))
            ->schema([
                Radio::make('hasil')->label('Hasil Wawancara')->options([
                    'lulus' => 'Lulus',
                    'tidak_lulus' => 'Tidak Lulus',
                ])->required(),
            ])
            ->action(function (array $data, $record): void {
                $round = $record->pendingInterviewRound();
                $invitee = $round ? $record->interviewInviteeForRound($round) : null;

                if (! $invitee) {
                    Notification::make()->title('Tidak ada tahap wawancara yang menunggu keputusan.')->warning()->send();

                    return;
                }

                $invitee->update(['result' => $data['hasil']]);
                $totalRounds = $record->jobOpening?->interview_rounds ?: 1;

                if ($data['hasil'] === 'tidak_lulus') {
                    // Gagal di tahap mana pun langsung berhenti - tidak ada tahap lanjutan.
                    $record->update(['interview_result' => 'tidak_lulus', 'status' => 'rejected']);
                    JobOpeningStageLog::record($record->job_opening_id, 'interview', 'rejected', 1);
                    ApplicationNotification::createDraftFor($record, CompanyMessageTemplate::EVENT_INTERVIEW_REJECTED);
                    Notification::make()->title('Hasil tersimpan: tidak lulus.')->success()->send();

                    return;
                }

                if ($round < $totalRounds) {
                    // Masih ada tahap berikutnya: status tetap `interview`, dan interview_result
                    // sengaja dibiarkan kosong karena itu menyimpan hasil AKHIR - baru diisi setelah
                    // tahap terakhir. Orang ini otomatis masuk daftar undangan tahap selanjutnya.
                    Notification::make()
                        ->title('Lulus ' . $this->roundLabel($round) . '. Menunggu dijadwalkan ke tahap berikutnya.')
                        ->success()->send();

                    return;
                }

                $record->update(['interview_result' => 'lulus', 'status' => 'offering']);
                JobOpeningStageLog::record($record->job_opening_id, 'interview', 'offering', 1);
                Notification::make()->title('Lulus seluruh tahap wawancara. Lanjut ke Offering.')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with([
                        'answers' => fn ($q) => $q->where('field_key', 'full_name'),
                        'interviewInvitee.schedule',
                        // Semua tahap ikut dimuat - dipakai kolom Tahap & Hasil per tahap, dan
                        // pendingInterviewRound() supaya tidak query ulang per baris.
                        'interviewInvitees.schedule',
                    ])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId)->where('status', 'interview'),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                TextColumn::make('interview_result')->label('Hasil')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus', default => 'Belum dicatat',
                    })
                    ->color(fn ($state) => match ($state) {
                        'lulus' => 'success', 'tidak_lulus' => 'danger', default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn ($record) => \App\Filament\Rekrutmen\Pages\ApplicantDetailPage::getUrl() . '?application=' . $record->id),
                $this->rescheduleOneWawancaraAction(),
                $this->tandaiHasilWawancaraAction(),
            ])
            ->toolbarActions([$this->kirimNotifBulkAction()])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat yang sudah dijadwalkan wawancara muncul di sini.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }
}
