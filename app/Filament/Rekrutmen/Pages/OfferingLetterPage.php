<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Models\Rekrutmen\JobOpeningOfferingCompensationItem;
use App\Models\Rekrutmen\JobOpeningOfferingCompensationRow;
use App\Models\Rekrutmen\JobOpeningStageLog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Offering Letter — port dari OfferingLetterPage project `rekrutmen` (FL3 -> FL4), DB
 * `recruitment`. File offering di disk `rekrutmen` (REKRUTMEN_STORAGE_PATH — WAJIB diisi di
 * .env, satu server dgn app rekrutmen). Fase 4: unggah surat rekruter, lihat surat masuk /
 * bertanda tangan, balas negosiasi, lanjutkan ke MCU/Onboarding. Email "surat siap" auto-kirim.
 *
 * Surat otomatis (2026-09-17, port dari rekrutmen): PDF disusun dari template company
 * (Benefit/Ketentuan Umum + penandatangan diatur di panel rekrutmen; Kompensasi per LOWONGAN,
 * bisa diatur di sini lewat "Atur Kompensasi" ATAU di form Lowongan) + data kandidat yang
 * sudah dikonfirmasi (offering_full_name dkk). DIOLAH LANGSUNG saat "Lihat Surat" diklik
 * (JobApplication::renderOfferingLetterPdf(), lewat CandidateDocumentProxyController) —
 * TIDAK disimpan, tidak ada tombol "generate" terpisah, selalu versi terbaru. Kandidat pun
 * unduh sendiri dari dashboard project rekrutmen dengan cara sama (live render), tidak
 * menunggu recruiter klik apa pun. Background & tanda tangan ditempel sebagai data URI
 * base64 (bukan path file) karena keduanya ada di root disk yang beda dari disk `rekrutmen`
 * privat HRIS — background dari disk itu juga (satu server), tanda tangan dari disk PUBLIK
 * rekrutmen makanya diambil lewat HTTP (services.rekrutmen.url).
 */
class OfferingLetterPage extends Page implements HasActions, HasForms, HasTable
{
    use \App\Filament\Rekrutmen\Concerns\PipelineTabs;
    use \App\Filament\Rekrutmen\Concerns\ScopedToCompany;
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.offering-letter-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Offering Letter';

    protected static ?string $title = 'Offering Letter';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 3;


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

    /** Gaya surat SGG (tabel) vs RS (list) buat lowongan yang lagi dipilih — lihat JobOpeningResource::isSggMode(). */
    private function isSggModeForCurrentOpening(): bool
    {
        $company = JobOpening::find($this->jobOpeningId)?->company;

        return $company?->offering_letter_template !== \App\Enum\Recruitment\OfferingLetterTemplate::Rs;
    }

    /** Atur komponen & nominal kompensasi lowongan ini langsung dari tab Offering Letter — sama data dengan Repeater di form Lowongan. */
    public function aturKompensasiAction(): Action
    {
        return Action::make('aturKompensasi')
            ->label('Atur Kompensasi')
            ->icon('heroicon-m-banknotes')
            ->color('gray')
            ->visible(fn () => $this->canDecide() && (bool) $this->jobOpeningId)
            ->schema([
                Repeater::make('rows')
                    ->label('')
                    ->schema([
                        Hidden::make('id'),
                        TextInput::make('label')->label('Komponen')->placeholder('Mis. Gaji Pokok')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('amount_orientasi')->label('Nominal — Orientasi')->placeholder('Angka polos, mis. 3000000')->maxLength(255),
                        TextInput::make('amount_kontrak')->label('Nominal — Kontrak')->placeholder('Angka polos, mis. 3500000')->maxLength(255),
                    ])
                    ->columns(2)
                    ->visible(fn () => $this->isSggModeForCurrentOpening())
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Komponen baru')
                    ->addActionLabel('Tambah komponen')
                    ->defaultItems(0),

                Repeater::make('mitraRows')
                    ->label('Komponen Mitra (Orientasi)')
                    ->schema([
                        Hidden::make('id'),
                        TextInput::make('label')->label('Komponen')->placeholder('Mis. Gaji Pokok')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('amount')->label('Nominal')->placeholder('Bebas teks atau kosongkan')->maxLength(255)->columnSpanFull(),
                    ])
                    ->visible(fn () => ! $this->isSggModeForCurrentOpening())
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Komponen baru')
                    ->addActionLabel('Tambah komponen Mitra')
                    ->defaultItems(0),

                Repeater::make('kontrakRows')
                    ->label('Komponen Kontrak')
                    ->schema([
                        Hidden::make('id'),
                        TextInput::make('label')->label('Komponen')->placeholder('Mis. Gaji Yang Didapatkan')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('amount')->label('Nominal')->placeholder('Bebas teks atau kosongkan')->maxLength(255)->columnSpanFull(),
                    ])
                    ->visible(fn () => ! $this->isSggModeForCurrentOpening())
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Komponen baru')
                    ->addActionLabel('Tambah komponen Kontrak')
                    ->defaultItems(0),
            ])
            ->fillForm(function () {
                $opening = JobOpening::find($this->jobOpeningId);
                $items = $opening?->offeringCompensationItems ?? collect();

                return [
                    'rows' => ($opening?->offeringCompensationRows ?? collect())
                        ->map(fn ($r) => [
                            'id' => $r->id, 'label' => $r->label,
                            'amount_orientasi' => $r->amount_orientasi, 'amount_kontrak' => $r->amount_kontrak,
                        ])->values()->all(),
                    'mitraRows' => $items->where('type', 'mitra')
                        ->map(fn ($r) => ['id' => $r->id, 'label' => $r->label, 'amount' => $r->amount])
                        ->values()->all(),
                    'kontrakRows' => $items->where('type', 'kontrak')
                        ->map(fn ($r) => ['id' => $r->id, 'label' => $r->label, 'amount' => $r->amount])
                        ->values()->all(),
                ];
            })
            ->modalHeading('Atur Kompensasi Offering Letter — Lowongan Ini')
            ->modalSubmitActionLabel('Simpan')
            ->action(function (array $data): void {
                if (! $this->jobOpeningId) {
                    return;
                }

                if ($this->isSggModeForCurrentOpening()) {
                    $rows = $data['rows'] ?? [];
                    $submittedIds = collect($rows)->pluck('id')->filter()->all();

                    JobOpeningOfferingCompensationRow::where('job_opening_id', $this->jobOpeningId)
                        ->whereNotIn('id', $submittedIds)
                        ->delete();

                    foreach ($rows as $i => $row) {
                        $values = [
                            'job_opening_id' => $this->jobOpeningId,
                            'label' => $row['label'],
                            'amount_orientasi' => $row['amount_orientasi'],
                            'amount_kontrak' => $row['amount_kontrak'],
                            'sort_order' => $i,
                        ];

                        if (! empty($row['id'])) {
                            JobOpeningOfferingCompensationRow::where('id', $row['id'])->update($values);
                        } else {
                            JobOpeningOfferingCompensationRow::create($values);
                        }
                    }
                } else {
                    $submitted = ['mitra' => $data['mitraRows'] ?? [], 'kontrak' => $data['kontrakRows'] ?? []];
                    $submittedIds = collect($submitted)->flatten(1)->pluck('id')->filter()->all();

                    JobOpeningOfferingCompensationItem::where('job_opening_id', $this->jobOpeningId)
                        ->whereNotIn('id', $submittedIds)
                        ->delete();

                    foreach ($submitted as $type => $rows) {
                        foreach ($rows as $i => $row) {
                            $values = [
                                'job_opening_id' => $this->jobOpeningId, 'type' => $type,
                                'label' => $row['label'], 'amount' => $row['amount'], 'sort_order' => $i,
                            ];

                            if (! empty($row['id'])) {
                                JobOpeningOfferingCompensationItem::where('id', $row['id'])->update($values);
                            } else {
                                JobOpeningOfferingCompensationItem::create($values);
                            }
                        }
                    }
                }

                Notification::make()->title('Kompensasi offering letter lowongan ini tersimpan')->success()->send();
            });
    }

    public function uploadSuratAction(): Action
    {
        return Action::make('uploadSurat')
            ->label(fn ($record) => $record->offering_letter_path ? 'Ganti Surat' : 'Unggah Surat')
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            ->visible(fn () => $this->canDecide())
            ->schema([
                FileUpload::make('file')
                    ->label('File Surat Penawaran (PDF / Word)')
                    ->disk('rekrutmen')
                    ->directory(fn ($record) => "offering-letters/{$record->id}/recruiter")
                    ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                    ->required(),
            ])
            ->action(function (array $data, $record): void {
                $record->update([
                    'offering_letter_path' => $data['file'],
                    'offering_letter_uploaded_at' => now(),
                ]);
                ApplicationNotification::createAndSend($record, CompanyMessageTemplate::EVENT_OFFERING_LETTER_READY);
                Notification::make()->title('Surat penawaran diunggah & email pemberitahuan terkirim.')->success()->send();
            });
    }

    /** Tampil selama kandidat belum konfirmasi data (nama/TTL/alamat) di dashboardnya — surat langsung bisa diunduh (diolah saat itu juga) setelah itu, tidak perlu aksi apa pun dari sini. */
    public function menungguDataKandidatAction(): Action
    {
        return Action::make('menungguDataKandidat')
            ->label('Menunggu Data Kandidat')
            ->icon('heroicon-m-clock')
            ->color('gray')
            ->disabled()
            ->visible(fn ($record) => ! $record->offering_data_confirmed_at)
            ->tooltip('Kandidat belum mengisi konfirmasi nama/tempat-tanggal lahir/alamat di dashboardnya (project rekrutmen).');
    }

    /**
     * Lihat surat (admin). Kalau recruiter pernah unggah manual, itu yang dibuka (override);
     * kalau tidak, dirender LANGSUNG dari data kandidat + config perusahaan/lowongan saat ini
     * (JobApplication::renderOfferingLetterPdf(), lewat CandidateDocumentProxyController —
     * tidak disimpan). Tidak ada tombol "generate" terpisah — selalu otomatis & selalu versi
     * terbaru begitu data kandidat sudah dikonfirmasi.
     */
    public function unduhSuratMasukAction(): Action
    {
        return Action::make('unduhSuratMasuk')
            ->label('Lihat Surat')
            ->icon('heroicon-m-eye')
            ->color('gray')
            ->visible(fn ($record) => (bool) $record->offering_letter_path || (bool) $record->offering_data_confirmed_at)
            ->url(fn ($record) => route('rekrutmen.file.offering-letter', ['jobApplication' => $record->id, 'type' => 'recruiter']))
            ->openUrlInNewTab();
    }

    public function unduhSuratSignedAction(): Action
    {
        return Action::make('unduhSuratSigned')
            ->label('Lihat Surat Bertanda Tangan')
            ->icon('heroicon-m-document-check')
            ->color('success')
            ->visible(fn ($record) => (bool) $record->offering_letter_signed_path)
            ->url(fn ($record) => route('rekrutmen.file.offering-letter', ['jobApplication' => $record->id, 'type' => 'signed']))
            ->openUrlInNewTab();
    }

    public function negosiasiAction(): Action
    {
        return Action::make('negosiasi')
            ->label(fn ($record) => 'Negosiasi (' . $record->offeringNegotiations->count() . ')')
            ->icon('heroicon-m-chat-bubble-left-right')
            ->color(fn ($record) => $record->offeringNegotiations->where('sender', 'applicant')->isNotEmpty() ? 'warning' : 'gray')
            ->modalHeading('Negosiasi Offering Letter')
            ->modalSubmitActionLabel('Kirim Pesan')
            ->schema(fn ($record) => [
                Placeholder::make('riwayat')->label('Riwayat')->content(new HtmlString(
                    $record->offeringNegotiations->isEmpty()
                        ? '<span style="color:#94a3b8">Belum ada percakapan. Kirim pesan pertama ke kandidat.</span>'
                        : collect($record->offeringNegotiations)->map(fn ($n) =>
                            '<div style="margin-bottom:8px;padding:8px 10px;border-radius:8px;background:'
                            . ($n->sender === 'applicant' ? '#f1f5f9' : '#eef2ff') . '"><strong>'
                            . ($n->sender === 'applicant' ? 'Kandidat' : 'Rekruter') . '</strong> <span style="color:#94a3b8">· '
                            . $n->created_at?->format('d M Y, H:i') . '</span><br>' . nl2br(e($n->message)) . '</div>'
                        )->implode('')
                )),
                Textarea::make('message')->label('Pesan')->rows(3)->required()->maxLength(2000),
            ])
            ->action(function (array $data, $record): void {
                $record->offeringNegotiations()->create(['sender' => 'recruiter', 'message' => $data['message']]);
                ApplicationNotification::notifyNegotiationReply($record, $data['message']);
                Notification::make()->title('Balasan negosiasi & email ke pelamar terkirim')->success()->send();
            });
    }

    public function terimaSuratAction(): Action
    {
        return Action::make('terimaSurat')
            ->label('Terima Surat')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn ($record) => $this->canDecide()
                && $record->offering_letter_signed_path !== null
                && $record->offering_signed_reviewed_at === null)
            ->requiresConfirmation()
            ->modalHeading('Terima Surat Penawaran Bertanda Tangan')
            ->modalDescription('Pastikan surat yang diunggah kandidat sudah benar. Kandidat akan diberi tahu lewat email bahwa surat diterima.')
            ->modalSubmitActionLabel('Terima')
            ->action(function ($record): void {
                $record->update(['offering_signed_reviewed_at' => now()]);
                ApplicationNotification::notifyOfferingSignedAccepted($record);
                Notification::make()->title('Surat diterima. Email pemberitahuan dikirim ke kandidat.')->success()->send();
            });
    }

    public function mintaKirimUlangAction(): Action
    {
        return Action::make('mintaKirimUlang')
            ->label('Minta Kirim Ulang')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('danger')
            ->visible(fn ($record) => $this->canDecide()
                && $record->offering_letter_signed_path !== null
                && $record->offering_signed_reviewed_at === null)
            ->modalHeading('Minta Kandidat Kirim Ulang')
            ->modalSubmitActionLabel('Minta Kirim Ulang')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan minta kirim ulang')
                    ->placeholder('Mis. tanda tangan tidak jelas / halaman tidak lengkap / salah dokumen.')
                    ->rows(3)
                    ->required()
                    ->maxLength(2000),
            ])
            ->action(function (array $data, $record): void {
                $record->update([
                    'offering_signed_revision_reason' => $data['reason'],
                    'offering_letter_signed_path' => null,
                    'offering_letter_signed_at' => null,
                    'offering_signed_reviewed_at' => null,
                ]);

                // Hapus row event lama supaya email revisi bisa dikirim ulang di siklus berikutnya.
                ApplicationNotification::where('job_application_id', $record->id)
                    ->where('event', CompanyMessageTemplate::EVENT_OFFERING_REVISION_REQUESTED)
                    ->delete();
                ApplicationNotification::notifyOfferingRevisionRequested($record, $data['reason']);

                Notification::make()->title('Permintaan kirim ulang & email ke kandidat terkirim.')->success()->send();
            });
    }

    public function lanjutkanAction(): Action
    {
        return Action::make('lanjutkan')
            ->label(fn ($record) => ($record->jobOpening->company?->requires_mcu ?? true) ? 'Lanjut ke MCU' : 'Lanjut ke Onboarding')
            ->icon('heroicon-m-arrow-right-circle')
            ->color('success')
            ->visible(fn ($record) => $this->canDecide() && $record->offering_signed_reviewed_at !== null)
            ->requiresConfirmation()
            ->modalDescription('Surat bertanda tangan sudah diterima. Lanjutkan kandidat ke tahap berikutnya.')
            ->action(function ($record): void {
                $next = ($record->jobOpening->company?->requires_mcu ?? true) ? 'mcu' : 'onboarding';
                $record->update(['status' => $next]);
                JobOpeningStageLog::record($record->job_opening_id, 'offering', $next, 1);
                Notification::make()->title('Kandidat dilanjutkan ke tahap berikutnya')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with(['answers' => fn ($q) => $q->where('field_key', 'full_name'), 'offeringNegotiations', 'jobOpening.company'])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId)->where('status', 'offering'),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                IconColumn::make('surat_diunggah')->label('Surat Diunggah')->boolean()
                    ->state(fn ($record) => $record->offering_letter_path !== null),
                IconColumn::make('surat_signed')->label('Sudah TTD Kandidat')->boolean()
                    ->state(fn ($record) => $record->offering_letter_signed_path !== null),
                TextColumn::make('offering_letter_signed_at')->label('Diunggah Kandidat')->dateTime('d M Y, H:i')->placeholder('—'),
                TextColumn::make('review_status')->label('Status Review')->badge()
                    ->state(fn ($record) => match (true) {
                        $record->offering_signed_reviewed_at !== null => 'Diterima',
                        $record->offering_letter_signed_path !== null => 'Menunggu review',
                        $record->offering_signed_revision_reason !== null => 'Minta kirim ulang',
                        default => 'Belum diunggah',
                    })
                    ->color(fn ($record) => match (true) {
                        $record->offering_signed_reviewed_at !== null => 'success',
                        $record->offering_letter_signed_path !== null => 'warning',
                        $record->offering_signed_revision_reason !== null => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                $this->menungguDataKandidatAction(),
                $this->uploadSuratAction(),
                $this->unduhSuratMasukAction(),
                $this->negosiasiAction(),
                $this->unduhSuratSignedAction(),
                $this->terimaSuratAction(),
                $this->mintaKirimUlangAction(),
                $this->lanjutkanAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat lulus wawancara muncul di sini untuk diberi offering letter.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
