<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\InterviewInvitee;
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
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Utilities\Set;
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

    /** Sesi wawancara yang sedang dibuka di halaman ini. */
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
        if (! $this->jobOpeningId) {
            return 1;
        }

        // Sesi yang SUDAH ada, plus satu slot kosong di belakangnya buat menjadwalkan sesi baru.
        // Tidak ada batas jumlah sesi - lihat catatan di currentRound().
        return (int) InterviewSchedule::where('job_opening_id', $this->jobOpeningId)->max('round') + 1;
    }

    /**
     * Sesi yang sedang dibuka di halaman ini.
     *
     * Jumlah sesi TIDAK dibatasi dan tidak diatur per lowongan: wawancara boleh dijadwalkan berapa
     * kali pun untuk orang yang sama. Sesi cuma penanda & nama ("Wawancara HR", "Wawancara User") -
     * keputusan lanjut/tidak BUKAN milik sesi, tapi menempel di status pelamarnya
     * (job_applications.interview_result + status), lihat tandaiHasilWawancaraAction().
     */
    public function currentRound(): int
    {
        return min(max(1, $this->activeRound), $this->getInterviewRounds());
    }

    public function updatedActiveRound(): void
    {
        $this->resetTable();
    }

    /**
     * Lowongan yang dipilih sudah punya komponen kompensasi offering letter.
     *
     * Dipakai menahan keputusan "Lulus": tanpa komponen ini surat penawarannya kosong, padahal
     * pelamarnya sudah terlanjur dikabari lolos dan diminta melengkapi datanya.
     */
    public function hasCompensationRows(): bool
    {
        $opening = $this->jobOpeningId ? JobOpening::find($this->jobOpeningId) : null;

        return $opening
            ? $opening->offeringCompensationRows()->exists() || $opening->offeringCompensationItems()->exists()
            : false;
    }

    /** Sesi ini sudah punya jadwal (draft maupun terbit), atau masih slot kosong buat sesi baru. */
    public function hasSession(int $round): bool
    {
        return $this->getInterviewDraft($round) !== null || $this->getPublishedInterview($round) !== null;
    }

    /**
     * Sebutan sesi ini di tombol & judul modal penjadwalan.
     *
     * Slot kosong: "Wawancara" kalau belum ada jadwal sama sekali, "Wawancara Berikutnya" kalau
     * sudah ada sesi sebelumnya - jadi tidak menyebut nomor tahap yang bikin seolah-olah jumlah
     * sesinya dipatok. Slot yang sudah punya jadwal memakai namanya sendiri, supaya HR tahu draft
     * mana yang sedang dia sunting.
     */
    public function scheduleLabel(int $round): string
    {
        if ($this->hasSession($round)) {
            return $this->roundLabel($round);
        }

        return $round > 1 ? 'Wawancara Berikutnya' : 'Wawancara';
    }

    /** Nama sesi: nama dari jadwalnya kalau HR sudah mengisi, kalau tidak "Wawancara Tahap N". */
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
     * Pelamar yang bisa diundang ke sesi ini.
     *
     * Dua kelompok: yang baru lolos seleksi berkas & kuis (belum masuk tahap wawancara), DAN yang
     * sudah di tahap wawancara tapi keputusannya belum ditetapkan - itu yang membuat satu orang bisa
     * dijadwalkan wawancara lagi. Tidak ada syarat "lulus sesi sebelumnya", karena hasil bukan milik
     * sesi; yang sudah diputuskan (lulus ke Offering atau tidak lulus) otomatis tidak ikut karena
     * statusnya sudah pindah.
     *
     * Yang sudah diundang ke sesi INI dikeluarkan supaya tidak dobel di sesi yang sama.
     */
    private function readyOptions(int $round = 1): array
    {
        if (! $this->jobOpeningId) {
            return [];
        }

        $sudahDiSesiIni = InterviewInvitee::whereHas(
            'schedule',
            fn ($q) => $q->where('job_opening_id', $this->jobOpeningId)->where('round', $round)
        )->pluck('job_application_id');

        return JobApplication::with(['answers' => fn ($q) => $q->where('field_key', 'full_name')])
            ->where('job_opening_id', $this->jobOpeningId)
            ->whereNotIn('id', $sudahDiSesiIni)
            ->where(fn ($q) => $q
                ->where(fn ($baru) => $baru->where('status', 'shortlisted')->where('quiz_result', 'lulus'))
                ->orWhere(fn ($lagi) => $lagi->where('status', 'interview')->whereNull('interview_result')))
            ->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->answers->firstWhere('field_key', 'full_name')?->value ?? '(tanpa nama)'])
            ->all();
    }

    public function jadwalWawancaraAction(): Action
    {
        $round = $this->currentRound();
        $options = $this->readyOptions($round);
        $draft = $this->getInterviewDraft($round);
        $tahap = $this->scheduleLabel($round);

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

    /**
     * Sesi wawancara yang pernah dijalani pelamar ini, buat dipilih di tombol Ubah Jadwal.
     *
     * @return array<string,string> id undangan => "Wawancara HR — 12 Okt 2026"
     */
    private function sessionOptions($record): array
    {
        return $record->interviewInvitees
            ->sortBy(fn ($inv) => $inv->round())
            ->mapWithKeys(fn ($inv) => [
                $inv->id => ($inv->schedule?->label() ?? 'Wawancara Tahap ' . $inv->round())
                    . ' — ' . ($inv->schedule?->interview_date?->translatedFormat('d M Y') ?? 'tanpa tanggal'),
            ])
            ->all();
    }

    /** Isi form jadwal dari satu undangan - dipakai saat modal dibuka & saat sesinya diganti. */
    private function sessionFormValues($invitee): array
    {
        return [
            'interview_date' => $invitee?->schedule?->interview_date?->format('Y-m-d'),
            'interview_time' => $invitee?->schedule?->interview_time,
            'location' => $invitee?->schedule?->location,
            'notes' => $invitee?->schedule?->notes,
        ];
    }

    public function rescheduleOneWawancaraAction(): Action
    {
        return Action::make('rescheduleOneWawancara')
            ->label('Ubah Jadwal')
            ->icon('heroicon-m-calendar')
            ->color('warning')
            ->visible(fn ($record) => $this->canDecide() && $record?->interviewInvitee && $record->interview_result === null)
            /*
             * Sejak wawancara bisa dijadwalkan berkali-kali, tombol ini harus menyebut sesi MANA yang
             * diubah - dulu diam-diam mengenai sesi terakhir. Sesinya dipilih sendiri oleh HR, default
             * sesi terakhir, dan tanggal/lokasi ikut terisi dari sesi yang dipilih.
             */
            ->fillForm(fn ($record) => [
                'invitee_id' => $record->interviewInvitee?->id,
            ] + $this->sessionFormValues($record->interviewInvitee))
            ->schema([
                Select::make('invitee_id')
                    ->label('Sesi yang Diubah')
                    ->options(fn ($record) => $this->sessionOptions($record))
                    ->required()
                    ->live()
                    ->selectablePlaceholder(false)
                    ->afterStateUpdated(function ($state, Set $set, $record) {
                        $invitee = $record->interviewInvitees->firstWhere('id', $state);
                        foreach ($this->sessionFormValues($invitee) as $key => $value) {
                            $set($key, $value);
                        }
                    })
                    ->helperText('Cuma sesi ini yang dipindah. Sesi lain pelamar ini tidak terpengaruh.'),
                DatePicker::make('interview_date')->label('Tanggal Wawancara Baru')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('interview_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi / Link Meeting'),
                Textarea::make('notes')->label('Catatan untuk Pelamar')->rows(2),
            ])
            ->modalHeading('Ubah Jadwal Wawancara — Pelamar Ini')
            ->modalDescription('Hanya pelamar ini yang dipindah ke jadwal baru. Peserta lain di sesi yang sama tidak terpengaruh.')
            ->modalSubmitActionLabel('Simpan & Kirim Pemberitahuan')
            ->action(function (array $data, $record): void {
                $invitee = $record->interviewInvitees->firstWhere('id', $data['invitee_id'])
                    ?? $record->interviewInvitee;

                if (! $invitee) {
                    return;
                }

                InterviewSchedule::rescheduleOne($invitee, $data);
                $this->resetTable();
                Notification::make()->title('Jadwal sesi tersebut diperbarui & email terkirim.')->success()->send();
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
            /*
             * SATU keputusan per pelamar, menempel di statusnya - bukan per sesi wawancara. Selama
             * belum dicatat, pelamarnya tetap di tahap wawancara dan boleh dijadwalkan lagi berapa
             * kali pun (lihat readyOptions()). Begitu dicatat, dia pindah status dan keluar dari
             * daftar ini.
             */
            ->visible(fn ($record) => $this->canDecide() && $record?->interview_result === null)
            ->modalHeading('Catat Hasil Wawancara')
            ->modalDescription(fn () => $this->hasCompensationRows()
                ? 'Keputusan ini menutup tahap wawancara pelamar ini. Kalau masih perlu sesi tambahan, jadwalkan dulu tanpa mencatat hasil.'
                : 'Komponen kompensasi lowongan ini belum diisi, jadi hasil "Lulus" belum bisa disimpan — surat penawarannya tidak akan punya isi. Lengkapi dulu di Lowongan > Kompensasi Offering Letter. Hasil "Tidak Lulus" tetap bisa disimpan.')
            ->fillForm(fn ($record) => ['catatan' => $record->interview_notes])
            ->schema([
                // Catatan menempel di keputusan, bukan di sesi tertentu - sama seperti hasilnya.
                RichEditor::make('catatan')
                    ->label('Catatan Wawancara')
                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                    ->helperText('Catatan internal HR, tidak dikirim ke pelamar.'),
                Radio::make('hasil')->label('Hasil Wawancara')->options([
                    'lulus' => 'Lulus',
                    'tidak_lulus' => 'Tidak Lulus',
                ])->required(),
            ])
            ->action(function (array $data, $record): void {
                $catatan = filled($data['catatan'] ?? null) ? $data['catatan'] : null;

                /*
                 * Lulus wawancara memindahkan pelamar ke tahap Offering dan langsung mengabari dia
                 * lewat email. Kalau komponen kompensasi lowongannya belum diisi, surat penawarannya
                 * tidak akan punya isi - jadi keputusannya ditahan di sini, bukan baru ketahuan waktu
                 * suratnya dibuat dan kandidatnya sudah terlanjur dijanjikan.
                 */
                if ($data['hasil'] === 'lulus' && ! $this->hasCompensationRows()) {
                    Notification::make()
                        ->title('Kompensasi lowongan belum diisi')
                        ->body('Lengkapi dulu Lowongan > Kompensasi Offering Letter sebelum meluluskan pelamar ke tahap Offering.')
                        ->danger()->persistent()->send();

                    return;
                }

                if ($data['hasil'] === 'lulus') {
                    $record->update([
                        'interview_result' => 'lulus',
                        'interview_notes' => $catatan,
                        'status' => 'offering',
                    ]);
                    JobOpeningStageLog::record($record->job_opening_id, 'interview', 'offering', 1);

                    // Kabari pelamarnya: lulus wawancara + diminta melengkapi data surat penawaran
                    // di dashboard. Sebelum ini lulus wawancara sama sekali tidak mengirim email dari
                    // HRIS - pelamarnya cuma diam menunggu tanpa tahu harus mengisi apa-apa.
                    ApplicationNotification::createAndSend($record, CompanyMessageTemplate::EVENT_INTERVIEW_PASSED);

                    Notification::make()
                        ->title('Lulus wawancara. Email pemberitahuan dikirim, pelamar diminta melengkapi data surat penawaran.')
                        ->success()->send();

                    return;
                }

                $record->update([
                    'interview_result' => 'tidak_lulus',
                    'interview_notes' => $catatan,
                    'status' => 'rejected',
                ]);
                JobOpeningStageLog::record($record->job_opening_id, 'interview', 'rejected', 1);
                ApplicationNotification::createDraftFor($record, CompanyMessageTemplate::EVENT_INTERVIEW_REJECTED);
                Notification::make()->title('Hasil tersimpan: tidak lulus.')->success()->send();
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
                        // Semua sesi ikut dimuat buat kolom Sesi, supaya tidak query ulang per baris.
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
                // Riwayat sesi yang pernah dia jalani. Cuma keterangan - tidak memengaruhi keputusan,
                // yang ada di kolom Hasil sebelah.
                TextColumn::make('sesi')->label('Sesi Wawancara')->badge()->color('info')
                    ->state(fn ($record) => $record->interviewInvitees
                        ->sortBy(fn ($inv) => $inv->round())
                        ->map(fn ($inv) => $inv->schedule?->label() ?? 'Wawancara Tahap ' . $inv->round())
                        ->values()->all() ?: ['Belum dijadwalkan']),
                // Catatan hasil wawancara - dipendekkan di kolom, lengkapnya di tooltip. Diisi
                // bersamaan dengan keputusannya di tombol Catat Hasil.
                TextColumn::make('interview_notes')->label('Catatan')
                    ->state(fn ($record) => filled($record->interview_notes)
                        ? str(strip_tags($record->interview_notes))->squish()->limit(60)
                        : '—')
                    ->tooltip(fn ($record) => filled($record->interview_notes)
                        ? str(strip_tags($record->interview_notes))->squish()->toString()
                        : null)
                    ->wrap(),
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
