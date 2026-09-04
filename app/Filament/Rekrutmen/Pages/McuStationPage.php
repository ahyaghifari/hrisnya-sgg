<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Enum\CustomPermissionEnum;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Isi Pemeriksaan MCU — port dari McuStationPage project `rekrutmen` (FL3 -> FL4), DB
 * `recruitment`. 4 form input digate permission HRIS INPUT_MCU_*. Upload file (EKG/lab/
 * radiologi) DIHILANGKAN sementara (butuh disk publik bersama app rekrutmen) — staf isi
 * hasil dalam bentuk teks.
 */
class McuStationPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.mcu-station-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Isi Pemeriksaan MCU';

    protected static ?string $title = 'Isi Pemeriksaan MCU';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 5;

    public ?string $companyId = null;
    public ?string $jobOpeningId = null;

    private const LAB_FIELDS = [
        'hemoglobin', 'leukosit', 'eritrosit', 'trombosit', 'hematokrit',
        'basofil', 'eosinofil', 'stab', 'segmen', 'limfosit', 'monosit',
        'mcv', 'mch', 'mchc', 'gds', 'gda',
    ];

    private const DOCTOR_FIELDS = [
        'kulit', 'rambut', 'visus_mata', 'buta_warna', 'lapang_pandang',
        'hidung', 'telinga', 'tonsil', 'faring', 'gigi', 'kelenjar_gondok',
        'suara_pernafasan', 'ronchi', 'wheezing', 'lainnya_paru', 'jantung',
        'hati', 'limpa', 'epigastrium', 'ginjal', 'lainnya_abdomen',
        'ekstremitas', 'neurologi',
    ];

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin'])
            || $u->canAny([
                CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value,
                CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value,
                CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value,
                CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value,
                CustomPermissionEnum::EDIT_INPUT_MCU->value,
            ]);
    }

    private function canStation(string $perm): bool
    {
        $u = Auth::user();

        return $u->hasRole(['Admin', 'Super Admin'])
            || $u->can($perm)
            || $u->can(CustomPermissionEnum::EDIT_INPUT_MCU->value);
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

    public function getCompanies(): \Illuminate\Support\Collection
    {
        return Company::where('requires_mcu', true)->orderBy('name')->get(['id', 'name']);
    }

    public function getJobOpenings(): \Illuminate\Support\Collection
    {
        return $this->companyId
            ? JobOpening::where('company_id', $this->companyId)->latest()->get(['id', 'title'])
            : collect();
    }

    public function isiPerawatAction(): Action
    {
        return Action::make('isiPerawat')
            ->label('Isi Perawat')
            ->icon('heroicon-m-heart')
            ->color('gray')
            ->visible(fn () => $this->canStation(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value))
            ->fillForm(fn ($record) => $record->mcuInvitee?->nurseAssessment?->toArray() ?? [])
            ->schema([
                Section::make('Vital Sign')->columns(3)->schema([
                    TextInput::make('tinggi_badan')->label('Tinggi Badan (cm)')->numeric()->required(),
                    TextInput::make('berat_badan')->label('Berat Badan (kg)')->numeric()->required(),
                    TextInput::make('tekanan_darah')->label('Tekanan Darah')->required(),
                    TextInput::make('nadi')->label('Nadi')->numeric()->required(),
                    TextInput::make('suhu')->label('Suhu (°C)')->numeric()->required(),
                    TextInput::make('pernafasan')->label('Pernafasan')->numeric()->required(),
                ]),
                Textarea::make('keluhan_kesehatan')->label('Keluhan Kesehatan')->rows(2),
                Section::make('Riwayat Penyakit Keluarga')->columns(2)->schema([
                    Toggle::make('rpk_hipertensi')->label('Hipertensi'),
                    Toggle::make('rpk_stroke')->label('Stroke'),
                    Toggle::make('rpk_jantung')->label('Jantung'),
                    Toggle::make('rpk_diabetes')->label('Diabetes'),
                    TextInput::make('rpk_lainnya')->label('Lainnya')->columnSpanFull(),
                ]),
                Textarea::make('riwayat_penyakit_dahulu')->label('Riwayat Penyakit Dahulu')->rows(2),
                Textarea::make('riwayat_operasi')->label('Riwayat Operasi')->rows(2),
                Section::make('Alergi')->columns(2)->schema([
                    Toggle::make('alergi_makanan')->label('Makanan'),
                    Toggle::make('alergi_obat')->label('Obat'),
                    Toggle::make('alergi_debu')->label('Debu'),
                    TextInput::make('alergi_lainnya')->label('Lainnya')->columnSpanFull(),
                ]),
                Section::make('Kebiasaan')->columns(2)->schema([
                    Toggle::make('kebiasaan_olahraga')->label('Olahraga'),
                    Toggle::make('kebiasaan_merokok')->label('Merokok'),
                    Toggle::make('kebiasaan_alkohol')->label('Alkohol'),
                    Toggle::make('kebiasaan_obat')->label('Obat-obatan'),
                    TextInput::make('kebiasaan_lainnya')->label('Lainnya')->columnSpanFull(),
                ]),
                Section::make('EKG')->columns(2)->schema([
                    TextInput::make('hasil_ekg')->label('Hasil EKG'),
                    Textarea::make('keterangan_ekg')->label('Keterangan EKG')->columnSpanFull(),
                ]),
                Textarea::make('catatan')->label('Catatan')->rows(2),
            ])
            ->modalWidth('3xl')
            ->action(function (array $data, $record): void {
                $record->mcuInvitee->nurseAssessment()->updateOrCreate(['mcu_invitee_id' => $record->mcuInvitee->id], $data);
                Notification::make()->title('Asesmen perawat tersimpan')->success()->send();
            });
    }

    public function isiLabAction(): Action
    {
        $fields = collect(self::LAB_FIELDS)->map(fn ($f) => TextInput::make($f)->label(ucfirst($f)))->all();

        return Action::make('isiLab')
            ->label('Isi Lab')
            ->icon('heroicon-m-beaker')
            ->color('gray')
            ->visible(fn () => $this->canStation(CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value))
            ->fillForm(fn ($record) => $record->mcuInvitee?->labResult?->toArray() ?? [])
            ->schema([
                Section::make('Hasil Laboratorium')->columns(4)->schema($fields),
                Section::make()->columns(2)->schema([
                    TextInput::make('hbsag')->label('HBsAg'),
                    TextInput::make('hiv')->label('HIV'),
                ]),
                Textarea::make('kesimpulan')->label('Kesimpulan')->rows(2),
            ])
            ->modalWidth('3xl')
            ->action(function (array $data, $record): void {
                $record->mcuInvitee->labResult()->updateOrCreate(['mcu_invitee_id' => $record->mcuInvitee->id], $data);
                Notification::make()->title('Hasil lab tersimpan')->success()->send();
            });
    }

    public function isiRadiologiAction(): Action
    {
        return Action::make('isiRadiologi')
            ->label('Isi Radiologi')
            ->icon('heroicon-m-camera')
            ->color('gray')
            ->visible(fn () => $this->canStation(CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value))
            ->fillForm(fn ($record) => $record->mcuInvitee?->radiologyResult?->toArray() ?? [])
            ->schema([
                Section::make()->columns(2)->schema([
                    TextInput::make('jenis_pemeriksaan')->label('Jenis Pemeriksaan'),
                    TextInput::make('foto_thorax')->label('Foto Thorax'),
                    TextInput::make('jantung')->label('Jantung'),
                    TextInput::make('paru_kanan')->label('Paru Kanan'),
                    TextInput::make('paru_kiri')->label('Paru Kiri'),
                    TextInput::make('diafragma')->label('Diafragma'),
                    TextInput::make('tulang')->label('Tulang'),
                ]),
                Textarea::make('kesimpulan')->label('Kesimpulan')->rows(2),
            ])
            ->modalWidth('2xl')
            ->action(function (array $data, $record): void {
                $record->mcuInvitee->radiologyResult()->updateOrCreate(['mcu_invitee_id' => $record->mcuInvitee->id], $data);
                Notification::make()->title('Hasil radiologi tersimpan')->success()->send();
            });
    }

    public function isiDokterAction(): Action
    {
        $fields = collect(self::DOCTOR_FIELDS)->map(fn ($f) => TextInput::make($f)->label(ucfirst(str_replace('_', ' ', $f))))->all();

        return Action::make('isiDokter')
            ->label('Isi Dokter')
            ->icon('heroicon-m-user-circle')
            ->color('gray')
            ->visible(fn () => $this->canStation(CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value))
            ->fillForm(fn ($record) => $record->mcuInvitee?->doctorExamination?->toArray() ?? [])
            ->schema([
                Section::make('Pemeriksaan Fisik')->columns(3)->schema($fields),
                Textarea::make('diagnosa')->label('Diagnosa')->rows(2),
                Textarea::make('rekomendasi')->label('Rekomendasi/Saran Dokter')->rows(2)
                    ->helperText('Keputusan final fit/unfit tetap oleh HRD di halaman MCU.'),
                Textarea::make('catatan')->label('Catatan')->rows(2),
            ])
            ->modalWidth('4xl')
            ->action(function (array $data, $record): void {
                $record->mcuInvitee->doctorExamination()->updateOrCreate(['mcu_invitee_id' => $record->mcuInvitee->id], $data);
                Notification::make()->title('Pemeriksaan dokter tersimpan')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with(['answers' => fn ($q) => $q->where('field_key', 'full_name'), 'mcuInvitee.nurseAssessment', 'mcuInvitee.labResult', 'mcuInvitee.radiologyResult', 'mcuInvitee.doctorExamination'])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId)->where('status', 'mcu')->whereHas('mcuInvitee'),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                IconColumn::make('nurse')->label('Perawat')->boolean()->state(fn ($r) => (bool) $r->mcuInvitee?->nurseAssessment),
                IconColumn::make('lab')->label('Lab')->boolean()->state(fn ($r) => (bool) $r->mcuInvitee?->labResult),
                IconColumn::make('radiology')->label('Radiologi')->boolean()->state(fn ($r) => (bool) $r->mcuInvitee?->radiologyResult),
                IconColumn::make('doctor')->label('Dokter')->boolean()->state(fn ($r) => (bool) $r->mcuInvitee?->doctorExamination),
            ])
            ->recordActions([
                ActionGroup::make([
                    $this->isiPerawatAction(),
                    $this->isiLabAction(),
                    $this->isiRadiologiAction(),
                    $this->isiDokterAction(),
                ])->label('Isi Pemeriksaan')->icon('heroicon-m-pencil-square'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat yang sudah diundang MCU muncul di sini.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}
