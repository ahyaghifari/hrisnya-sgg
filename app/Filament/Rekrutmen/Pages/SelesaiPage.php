<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Services\Rekrutmen\CandidateOnboardingService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Radio;
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
 * Selesai — daftar kandidat resmi diterima (status `passed`). Port dari SelesaiPage project
 * `rekrutmen` (FL3 -> FL4), DB `recruitment`. Aksi "Buat Akun Karyawan" (pilih lama orientasi)
 * pindah ke sini dari halaman Livewire portal lama.
 */
class SelesaiPage extends Page implements HasActions, HasForms, HasTable
{
    use \App\Filament\Rekrutmen\Concerns\PipelineTabs;
    use \App\Filament\Rekrutmen\Concerns\ScopedToCompany;
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.selesai-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Selesai';

    protected static ?string $title = 'Selesai — Diterima Jadi Karyawan';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 7;


    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('View:JobOpenings');
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

    public function buatAkunKaryawanAction(): Action
    {
        return Action::make('buatAkunKaryawan')
            ->label('Buat Akun Karyawan')
            ->icon('heroicon-m-user-plus')
            ->color('primary')
            ->visible(fn () => filament()->auth()->user()->can('create', \App\Models\User::class))
            ->modalHeading('Buat Akun Karyawan HRIS')
            ->modalDescription('Akun karyawan dibuat dari data diri kandidat. Periode orientasi dibuat sekali sesuai pilihan di bawah.')
            ->schema([
                Radio::make('orientationMonths')->label('Lama Orientasi')
                    ->options(collect(CandidateOnboardingService::ORIENTATION_MONTHS_OPTIONS)
                        ->mapWithKeys(fn ($m) => [$m => "{$m} bulan"])->all())
                    ->default(3)->required(),
            ])
            ->action(function (array $data, $record): void {
                $res = app(CandidateOnboardingService::class)
                    ->createEmployeeFromCandidate($record->id, (int) $data['orientationMonths']);

                Notification::make()
                    ->title($res['ok'] ? 'Akun karyawan siap' : 'Gagal membuat akun')
                    ->body($res['message'])
                    ->{$res['ok'] ? 'success' : 'danger'}()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with(['answers' => fn ($q) => $q->where('field_key', 'full_name'), 'candidate', 'jobOpening'])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId)->where('status', 'passed'),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                TextColumn::make('jobOpening.title')->label('Lowongan')->searchable(),
                IconColumn::make('data_diri')->label('Data Diri Kandidat')->boolean()
                    ->state(fn ($record) => filled($record->candidate?->nik)),
                TextColumn::make('updated_at')->label('Tanggal Diterima')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('lihat')->label('Detail')->icon('heroicon-m-eye')->color('gray')
                    ->url(fn ($record) => \App\Filament\Rekrutmen\Pages\ApplicantDetailPage::getUrl() . '?application=' . $record->id),
                $this->buatAkunKaryawanAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat yang sudah resmi diterima jadi karyawan muncul di sini.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}
