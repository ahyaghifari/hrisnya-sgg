<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Models\Rekrutmen\JobOpeningStageLog;
use App\Models\Rekrutmen\McuInvitee;
use App\Models\Rekrutmen\McuSchedule;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Medical Check Up — port dari McuPage project `rekrutmen` (FL3 -> FL4), DB `recruitment`.
 * Cuma perusahaan `requires_mcu`. Jadwal draft -> terapkan (status `mcu` + email undangan
 * auto-kirim), putuskan fit/unfit (fit -> `onboarding` + email auto; unfit -> `rejected` +
 * draft notif, kirim lewat aksi bulk "Kirim Email Pemberitahuan"). Input 4 stasiun ada di
 * modul portal MCU (App\Livewire\Rekrutmen\Mcu\*, route rekrutmen.mcu.*).
 */
class McuPage extends Page implements HasActions, HasForms, HasTable
{
    use \App\Filament\Rekrutmen\Concerns\PipelineTabs;
    use \App\Filament\Rekrutmen\Concerns\ScopedToCompany;
    use \App\Filament\Rekrutmen\Concerns\SendsApplicantNotifications;
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.mcu-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'MCU';

    protected static ?string $title = 'Medical Check Up';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 4;


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

    protected function companiesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Company::query()->where('requires_mcu', true)->orderBy('name');
    }

    public function getJobOpenings(): \Illuminate\Support\Collection
    {
        return $this->companyId
            ? JobOpening::where('company_id', $this->companyId)->latest()->get(['id', 'title'])
            : collect();
    }

    public function getReadyCount(): int
    {
        return $this->jobOpeningId
            ? JobApplication::where('job_opening_id', $this->jobOpeningId)->where('status', 'mcu')->whereDoesntHave('mcuInvitee')->count()
            : 0;
    }

    public function getMcuDraft(): ?McuSchedule
    {
        return $this->jobOpeningId ? McuSchedule::draftForOpening($this->jobOpeningId) : null;
    }

    public function getPublishedMcu(): ?McuSchedule
    {
        return $this->jobOpeningId ? McuSchedule::publishedForOpening($this->jobOpeningId) : null;
    }

    private function readyOptions(): array
    {
        if (! $this->jobOpeningId) {
            return [];
        }

        return JobApplication::with(['answers' => fn ($q) => $q->where('field_key', 'full_name')])
            ->where('job_opening_id', $this->jobOpeningId)->where('status', 'mcu')->whereDoesntHave('mcuInvitee')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->answers->firstWhere('field_key', 'full_name')?->value ?? '(tanpa nama)'])
            ->all();
    }

    public function jadwalMcuAction(): Action
    {
        $options = $this->readyOptions();
        $draft = $this->getMcuDraft();

        return Action::make('jadwalMcu')
            ->label('Jadwalkan MCU (' . count($options) . ' kandidat)')
            ->icon('heroicon-m-calendar-days')
            ->color('info')
            ->visible(fn () => $this->canDecide() && count($options) > 0)
            ->fillForm([
                'mcu_date' => $draft?->mcu_date?->format('Y-m-d'),
                'mcu_time' => $draft?->mcu_time,
                'location' => $draft?->location,
                'notes' => $draft?->notes,
                'invited_ids' => $draft ? $draft->invitees()->pluck('job_application_id')->all() : [],
            ])
            ->schema([
                DatePicker::make('mcu_date')->label('Tanggal MCU')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('mcu_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi Klinik/RS'),
                Textarea::make('notes')->label('Catatan untuk Kandidat')->rows(2),
                CheckboxList::make('invited_ids')->label('Pilih Kandidat')
                    ->options($options)->searchable()->bulkToggleable()->columns(1),
            ])
            ->modalHeading('Jadwalkan MCU')
            ->modalSubmitActionLabel('Simpan Draft Jadwal')
            ->action(function (array $data): void {
                if (! $this->jobOpeningId) {
                    return;
                }
                McuSchedule::saveDraft($this->jobOpeningId, $data, $data['invited_ids'] ?? []);
                Notification::make()->title('Draft jadwal MCU tersimpan.')->warning()->send();
            });
    }

    public function rescheduleMcuAction(): Action
    {
        $published = $this->getPublishedMcu();

        return Action::make('rescheduleMcu')
            ->label('Ubah Jadwal — Semua')
            ->icon('heroicon-m-calendar')
            ->color('warning')
            ->visible(fn () => $this->canDecide() && $published !== null)
            ->fillForm([
                'mcu_date' => $published?->mcu_date?->format('Y-m-d'),
                'mcu_time' => $published?->mcu_time,
                'location' => $published?->location,
                'notes' => $published?->notes,
            ])
            ->schema([
                DatePicker::make('mcu_date')->label('Tanggal MCU Baru')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('mcu_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi Klinik/RS'),
                Textarea::make('notes')->label('Catatan untuk Kandidat')->rows(2),
            ])
            ->modalHeading('Ubah Jadwal MCU — Semua Peserta')
            ->modalDescription('Semua kandidat yang masih di tahap MCU dipindah ke jadwal baru & dapat email pemberitahuan.')
            ->modalSubmitActionLabel('Simpan & Kirim Pemberitahuan')
            ->action(function (array $data): void {
                if (! $this->jobOpeningId) {
                    return;
                }
                $n = McuSchedule::rescheduleOpening($this->jobOpeningId, $data);
                $this->resetTable();
                Notification::make()->title("Jadwal MCU diperbarui. {$n} kandidat dinotifikasi via email.")->success()->send();
            });
    }

    public function rescheduleOneMcuAction(): Action
    {
        return Action::make('rescheduleOneMcu')
            ->label('Ubah Jadwal')
            ->icon('heroicon-m-calendar')
            ->color('warning')
            ->visible(fn ($record) => $this->canDecide() && $record?->mcuInvitee && ($record->mcuInvitee->status_mcu === null || $record->mcuInvitee->status_mcu === 'pending'))
            ->fillForm(fn ($record) => [
                'mcu_date' => $record->mcuInvitee?->schedule?->mcu_date?->format('Y-m-d'),
                'mcu_time' => $record->mcuInvitee?->schedule?->mcu_time,
                'location' => $record->mcuInvitee?->schedule?->location,
                'notes' => $record->mcuInvitee?->schedule?->notes,
            ])
            ->schema([
                DatePicker::make('mcu_date')->label('Tanggal MCU Baru')->required()->native(false)->displayFormat('d/m/Y'),
                TimePicker::make('mcu_time')->label('Waktu')->seconds(false),
                TextInput::make('location')->label('Lokasi Klinik/RS'),
                Textarea::make('notes')->label('Catatan untuk Kandidat')->rows(2),
            ])
            ->modalHeading('Ubah Jadwal MCU — Kandidat Ini')
            ->modalDescription('Hanya kandidat ini yang dipindah ke jadwal baru. Peserta lain tidak terpengaruh.')
            ->modalSubmitActionLabel('Simpan & Kirim Pemberitahuan')
            ->action(function (array $data, $record): void {
                if (! $record?->mcuInvitee) {
                    return;
                }
                McuSchedule::rescheduleOne($record->mcuInvitee, $data);
                $this->resetTable();
                Notification::make()->title('Jadwal MCU kandidat ini diperbarui & email terkirim.')->success()->send();
            });
    }

    public function publishMcuAction(): Action
    {
        $draft = $this->getMcuDraft();
        $invitees = $draft ? $draft->invitees()->with(['jobApplication.answers'])->get() : collect();

        $namesHtml = $invitees->map(fn ($inv) => $inv->jobApplication->answers->firstWhere('field_key', 'full_name')?->value ?? '—')
            ->map(fn ($name) => '<li style="padding:6px 10px;border-bottom:1px solid #e5e7eb;font-size:13px;">' . e($name) . '</li>')
            ->join('');

        return Action::make('publishMcu')
            ->label('Terapkan Jadwal (' . $invitees->count() . ')')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn () => $this->canDecide() && $draft !== null)
            ->modalHeading('Terapkan Jadwal MCU?')
            ->modalDescription(fn () => 'Tanggal: ' . ($draft?->mcu_date?->translatedFormat('d F Y') ?? '-')
                . '  |  Waktu: ' . ($draft?->mcu_time ? substr($draft->mcu_time, 0, 5) : '-')
                . ($draft?->location ? '  |  ' . $draft->location : ''))
            ->schema([
                Placeholder::make('diundang')->label($invitees->count() . ' Kandidat Diundang')
                    ->content(new HtmlString('<ul style="border:1px solid #e5e7eb;border-radius:8px;max-height:200px;overflow-y:auto;margin:0;padding:0;list-style:none;">' . ($namesHtml ?: '<li style="padding:6px 10px;font-size:13px;color:#9ca3af;">Tidak ada</li>') . '</ul>')),
            ])
            ->modalSubmitActionLabel('Terapkan')
            ->action(function (): void {
                $draft = $this->getMcuDraft();
                if (! $draft) {
                    return;
                }
                $draft->apply();
                $this->resetTable();
                Notification::make()->title('Jadwal MCU diterapkan. Undangan MCU otomatis dikirim ke email pelamar.')->success()->send();
            });
    }

    /**
     * Berkas hasil MCU per stasiun untuk satu peserta.
     *
     * @return array<string,string> label stasiun => path di disk local
     */
    private function mcuFiles(string $inviteeId): array
    {
        $invitee = McuInvitee::with(['nurseAssessment', 'labResult', 'radiologyResult', 'doctorExamination'])
            ->find($inviteeId);

        return array_filter([
            'Asesmen Perawat' => $invitee?->nurseAssessment?->file_path,
            'Laboratorium' => $invitee?->labResult?->file_path,
            'Radiologi' => $invitee?->radiologyResult?->file_path,
            'Pemeriksaan Dokter' => $invitee?->doctorExamination?->file_path,
        ]);
    }

    /**
     * Unduh satu berkas hasil MCU. Stasiun disebut lewat nama & peserta lewat id - path tidak pernah
     * diterima dari request, karena path bebas berarti berkas kandidat lain ikut bisa dibaca.
     */
    public function unduhBerkasMcu(string $inviteeId, string $stasiun)
    {
        abort_unless(static::canAccess(), 403);

        $path = $this->mcuFiles($inviteeId)[$stasiun] ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    /**
     * Hasil MCU ditampilkan sebagai MODAL, bukan halaman sendiri (keputusan user 2026-09-30):
     * isinya sekarang cuma berkas per stasiun + rekomendasi dokter, tidak sepadan dengan pindah
     * halaman. HasilMcuDetailPage dihapus bersamaan.
     */
    public function lihatHasilMcuAction(): Action
    {
        return Action::make('lihatHasilMcu')
            ->label('Hasil MCU')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('gray')
            ->visible(fn ($record) => $record->mcuInvitee?->hasAnyMcuResult())
            ->modalHeading('Hasil MCU')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema(fn ($record) => [
                Placeholder::make('hasil_mcu')
                    ->hiddenLabel()
                    ->content(fn () => new HtmlString($this->mcuResultHtml($record))),
            ]);
    }

    /** Isi modal hasil MCU: rekomendasi dokter + daftar berkas dengan tombol unduh per stasiun. */
    private function mcuResultHtml($record): string
    {
        $invitee = $record->mcuInvitee;
        $rekomendasi = $invitee?->doctorExamination?->rekomendasi_fit;
        $html = '';

        if ($rekomendasi) {
            $warna = $rekomendasi === 'fit' ? '#047C54' : '#ef4444';
            $teks = $rekomendasi === 'fit' ? 'Fit' : 'Tidak Fit';
            $html .= '<div style="margin-bottom:14px;font-size:13px;">Rekomendasi dokter: '
                . '<strong style="color:' . $warna . ';">' . $teks . '</strong>'
                . ' <span style="color:#9ca3af;">— keputusan MCU tetap ditetapkan HRD.</span></div>';
        }

        $files = $this->mcuFiles($invitee->id);

        if ($files === []) {
            return $html . '<p style="font-size:13px;color:#9ca3af;margin:0;">Belum ada berkas hasil yang diunggah.</p>';
        }

        foreach ($files as $stasiun => $path) {
            $html .= '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-top:1px solid #e5e7eb;">'
                . '<span style="font-size:13px;">' . e($stasiun) . '</span>'
                . '<button type="button" wire:click="unduhBerkasMcu(\'' . e($invitee->id) . '\', \'' . e($stasiun) . '\')"'
                . ' style="font-size:12.5px;font-weight:600;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer;">Unduh</button>'
                . '</div>';
        }

        return $html;
    }

    public function putuskanHasilMcuAction(): Action
    {
        return Action::make('putuskanHasilMcu')
            ->label('Putuskan Hasil MCU')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('gray')
            ->visible(fn ($record) => $this->canDecide()
                && $record->mcuInvitee
                && $record->mcuInvitee->status_mcu === 'pending'
                && $record->mcuInvitee->doctorExamDone())
            ->schema(fn ($record) => [
                Placeholder::make('rekomendasi_dokter')->label('Rekomendasi Dokter')
                    ->content($record->mcuInvitee?->doctorExamination?->rekomendasi ?: '—'),
                Radio::make('hasil')->label('Keputusan MCU')->options([
                    'fit' => 'Fit (Lulus)',
                    'unfit' => 'Unfit (Tidak Lulus)',
                ])->required(),
                Textarea::make('catatan_mcu')->label('Catatan Keputusan')->rows(3),
            ])
            ->action(function (array $data, $record): void {
                $invitee = $record->mcuInvitee;
                if (! $invitee) {
                    return;
                }

                $invitee->update(['status_mcu' => $data['hasil'], 'catatan_mcu' => $data['catatan_mcu'] ?? null]);

                $next = $data['hasil'] === 'fit' ? 'onboarding' : 'rejected';
                $record->update(['status' => $next]);
                JobOpeningStageLog::record($record->job_opening_id, 'mcu', $next, 1);

                if ($next === 'rejected') {
                    ApplicationNotification::createDraftFor($record, CompanyMessageTemplate::EVENT_MCU_REJECTED);
                } else {
                    ApplicationNotification::createAndSend($record, CompanyMessageTemplate::EVENT_MCU_PASSED);
                }

                Notification::make()->title('Keputusan MCU tersimpan')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with(['answers' => fn ($q) => $q->where('field_key', 'full_name'), 'mcuInvitee.schedule', 'mcuInvitee.nurseAssessment', 'mcuInvitee.labResult', 'mcuInvitee.radiologyResult', 'mcuInvitee.doctorExamination'])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId)->where('status', 'mcu'),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                IconColumn::make('nurse')->label('Perawat')->boolean()->state(fn ($record) => (bool) $record?->mcuInvitee?->nurseAssessment),
                IconColumn::make('lab')->label('Lab')->boolean()->state(fn ($record) => (bool) $record?->mcuInvitee?->labResult),
                IconColumn::make('radiology')->label('Radiologi')->boolean()->state(fn ($record) => (bool) $record?->mcuInvitee?->radiologyResult),
                IconColumn::make('doctor')->label('Dokter')->boolean()->state(fn ($record) => (bool) $record?->mcuInvitee?->doctorExamination),
                TextColumn::make('status_mcu')->label('Keputusan MCU')->badge()
                    ->state(fn ($record) => $record->mcuInvitee?->status_mcu)
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fit' => 'Fit', 'unfit' => 'Unfit', default => 'Belum diputuskan',
                    })
                    ->color(fn ($state) => match ($state) {
                        'fit' => 'success', 'unfit' => 'danger', default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('lihat')->label('Detail')->icon('heroicon-m-eye')->color('gray')
                    ->url(fn ($record) => \App\Filament\Rekrutmen\Pages\ApplicantDetailPage::getUrl() . '?application=' . $record->id),
                $this->lihatHasilMcuAction(),
                $this->rescheduleOneMcuAction(),
                $this->putuskanHasilMcuAction(),
            ])
            ->toolbarActions([$this->kirimNotifBulkAction()])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat yang lulus wawancara & perusahaannya mewajibkan MCU muncul di sini.')
            ->emptyStateIcon('heroicon-o-heart');
    }
}
