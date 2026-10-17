<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
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
use UnitEnum;

/**
 * TTD & Onboarding — port dari TtdOnboardingPage project `rekrutmen` (FL3 -> FL4), DB
 * `recruitment`. Kandidat status `onboarding`; "Tandai Selesai" -> is_onboarded + status
 * `passed` (state machine booted() auto-close lowongan) + draft notif ONBOARDING_INVITE
 * (email undangan auto-kirim). Digate progress persetujuan onboarding kandidat.
 */
class TtdOnboardingPage extends Page implements HasActions, HasForms, HasTable
{
    use \App\Filament\Rekrutmen\Concerns\PipelineTabs;
    use \App\Filament\Rekrutmen\Concerns\ScopedToCompany;
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.ttd-onboarding-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'TTD & Onboarding';

    protected static ?string $title = 'TTD & Onboarding';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 6;


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

    /** @return array{0:int,1:int} [disetujui, total] persetujuan efektif kandidat ini. */
    private function agreementProgress($record): array
    {
        $total = $record->jobOpening?->effectiveAgreements()->count() ?? 0;
        $approved = $record->candidate
            ? $record->candidate->agreements->filter(fn ($a) => $a->company_agreement_id || $a->job_opening_agreement_id)->count()
            : 0;

        return [$approved, $total];
    }

    /**
     * Tetapkan / ubah tanggal onboarding + kirim (ulang) email undangan onboarding.
     * Tidak mengubah status — "Tandai Selesai" yang menandai kandidat resmi jadi karyawan
     * setelah dia benar-benar datang & tanda tangan kontrak fisik.
     */
    public function aturJadwalOnboardingAction(): Action
    {
        return Action::make('aturJadwalOnboarding')
            ->label(fn ($record) => $record->onboarding_date ? 'Ubah Jadwal Onboarding' : 'Atur Jadwal Onboarding')
            ->icon('heroicon-m-calendar-days')
            ->color('primary')
            ->visible(fn () => $this->canDecide())
            ->disabled(function ($record): bool {
                [$approved, $total] = $this->agreementProgress($record);

                return $approved < $total;
            })
            ->tooltip(function ($record): ?string {
                [$approved, $total] = $this->agreementProgress($record);

                return $approved < $total
                    ? "Kandidat belum menyetujui semua persetujuan ({$approved}/{$total})."
                    : null;
            })
            ->modalHeading('Jadwal Onboarding')
            ->modalDescription('Tanggal ini dikirim ke kandidat lewat email undangan onboarding, dan jadi Tanggal Bergabung saat akun karyawan dibuat.')
            ->modalSubmitActionLabel('Simpan & Kirim Email')
            ->schema([
                DatePicker::make('onboarding_date')
                    ->label('Tanggal Onboarding')
                    ->required()
                    ->native(false)
                    ->minDate(today())
                    ->default(fn ($record) => $record->onboarding_date ?? today()->addDays(7)),
            ])
            ->action(function (array $data, $record): void {
                $record->update(['onboarding_date' => $data['onboarding_date']]);

                // Hapus row lama supaya email undangan bisa dikirim ulang saat jadwal diubah.
                ApplicationNotification::where('job_application_id', $record->id)
                    ->where('event', CompanyMessageTemplate::EVENT_ONBOARDING_INVITE)
                    ->delete();
                ApplicationNotification::createAndSend($record, CompanyMessageTemplate::EVENT_ONBOARDING_INVITE, [
                    'onboarding_date' => Carbon::parse($data['onboarding_date'])->translatedFormat('l, d F Y'),
                ]);

                $this->resetTable();
                Notification::make()->title('Jadwal onboarding disimpan & email undangan dikirim ke kandidat.')->success()->send();
            });
    }

    public function tandaiOnboardingSelesaiAction(): Action
    {
        return Action::make('tandaiOnboardingSelesai')
            ->label('Tandai Selesai')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->visible(fn () => $this->canDecide())
            ->disabled(function ($record): bool {
                [$approved, $total] = $this->agreementProgress($record);

                return $approved < $total || $record->onboarding_date === null;
            })
            ->tooltip(function ($record): ?string {
                [$approved, $total] = $this->agreementProgress($record);

                if ($record->onboarding_date === null) {
                    return 'Atur jadwal onboarding dulu.';
                }

                return $approved < $total ? "Kandidat belum menyetujui semua persetujuan ({$approved}/{$total})." : null;
            })
            ->requiresConfirmation()
            ->modalDescription('Tandai kandidat resmi menjadi karyawan — lakukan setelah kandidat benar-benar hadir onboarding & menandatangani kontrak fisik.')
            ->action(function ($record): void {
                $record->candidate?->update(['is_onboarded' => true]);
                $record->update(['status' => 'passed']);

                $this->resetTable();
                Notification::make()->title('Kandidat resmi menjadi karyawan')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with(['answers' => fn ($q) => $q->where('field_key', 'full_name'), 'candidate.agreements', 'jobOpening.company.agreements', 'jobOpening.agreements'])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId)->where('status', 'onboarding'),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                IconColumn::make('data_diri')->label('Data Diri Kandidat')->boolean()
                    ->state(fn ($record) => filled($record->candidate?->nik)),
                TextColumn::make('persetujuan')->label('Persetujuan')->badge()
                    ->state(function ($record) {
                        [$a, $t] = $this->agreementProgress($record);

                        return "{$a}/{$t}";
                    })
                    ->color(fn ($record) => $this->agreementProgress($record)[0] >= $this->agreementProgress($record)[1] ? 'success' : 'warning'),
                TextColumn::make('onboarding_date')->label('Jadwal Onboarding')->date('d M Y')
                    ->placeholder('Belum diatur')->color(fn ($record) => $record->onboarding_date ? null : 'danger'),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('lihat')->label('Detail')->icon('heroicon-m-eye')->color('gray')
                    ->url(fn ($record) => \App\Filament\Rekrutmen\Pages\ApplicantDetailPage::getUrl() . '?application=' . $record->id),
                Action::make('nomorPerjanjianMitra')
                    ->label(fn ($record) => $record->mitraAgreementNumber()?->number ?? 'Buat Nomor Perjanjian')
                    ->tooltip(fn ($record) => $record->agreementKind() === \App\Models\Rekrutmen\MitraAgreementNumber::KIND_MITRA ? 'Perjanjian Kerja Mitra' : 'PKWTT')
                    ->icon('heroicon-m-hashtag')->color('gray')
                    ->visible(fn ($record) => $this->canDecide() && $record->offering_data_confirmed_at)
                    ->modalHeading(fn ($record) => $record->mitraAgreementNumber() ? 'Ubah Nomor Perjanjian' : 'Buat Nomor Perjanjian')
                    ->modalDescription(fn ($record) => $record->mitraAgreementNumber()
                        ? 'Nomor bisa diubah bebas kapan saja. Urutan otomatis nomor berikutnya tidak ikut berubah.'
                        : ($record->agreementKind() === \App\Models\Rekrutmen\MitraAgreementNumber::KIND_PKWTT
                            ? 'Nomor lengkap diisi/diedit langsung. Usulan urut otomatis sudah terisi (reset 001 tiap ganti tahun), lengkapi bagian "…".'
                            : 'Nomor urut otomatis: ' . sprintf('%03d', \App\Models\Rekrutmen\MitraAgreementNumber::nextSeq($record->agreementKind())) . ' (reset 001 tiap ganti tahun). Isi segmen tengah (titik-titik) secara manual.'))
                    ->modalSubmitActionLabel(fn ($record) => $record->mitraAgreementNumber() ? 'Simpan Nomor' : 'Buat Nomor')
                    ->schema(function ($record) {
                        $existing = $record->mitraAgreementNumber();

                        // PKWTT: satu kolom nomor lengkap (usulan terisi, diedit bebas). Mitra RS: hanya segmen tengah.
                        if ($existing || $record->agreementKind() === \App\Models\Rekrutmen\MitraAgreementNumber::KIND_PKWTT) {
                            return [\Filament\Forms\Components\TextInput::make('number')->label('Nomor perjanjian')
                                ->default($existing?->number ?? \App\Models\Rekrutmen\MitraAgreementNumber::suggest($record->agreementKind()))
                                ->required()->maxLength(100)];
                        }

                        return [\Filament\Forms\Components\TextInput::make('segment')->label('Segmen tengah (…)')
                            ->helperText('Contoh: bulan romawi, mis. X')->required()->maxLength(20)];
                    })
                    ->action(function (array $data, $record): void {
                        $existing = $record->mitraAgreementNumber();
                        $n = $existing
                            ? tap($existing)->update(['number' => trim($data['number'])])
                            : \App\Models\Rekrutmen\MitraAgreementNumber::issue($record->id, $record->agreementKind(), trim($data['segment'] ?? ''), isset($data['number']) ? trim($data['number']) : null);
                        Notification::make()->title(($existing ? 'Nomor diubah: ' : 'Nomor dibuat: ') . $n->number)->success()->send();
                    }),
                Action::make('perjanjianMitra')->label(fn ($record) => $record->agreementKind() === \App\Models\Rekrutmen\MitraAgreementNumber::KIND_MITRA ? 'Perjanjian Mitra' : 'PKWTT')->icon('heroicon-m-document-text')->color('gray')
                    ->visible(fn ($record) => (bool) $record->offering_data_confirmed_at)
                    ->url(fn ($record) => route('rekrutmen.file.perjanjian-mitra', $record->id), shouldOpenInNewTab: true),
                $this->aturJadwalOnboardingAction(),
                $this->tandaiOnboardingSelesaiAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat lulus MCU (atau wawancara, kalau MCU tidak wajib) muncul di sini.')
            ->emptyStateIcon('heroicon-o-pencil-square');
    }
}
