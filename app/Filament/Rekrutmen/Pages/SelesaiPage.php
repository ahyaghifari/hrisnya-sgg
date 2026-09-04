<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use Filament\Actions\Action;
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
 * Selesai — daftar kandidat resmi diterima (status `passed`). Read-only. Port dari
 * SelesaiPage project `rekrutmen` (FL3 -> FL4), DB `recruitment`.
 */
class SelesaiPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.selesai-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Selesai';

    protected static ?string $title = 'Selesai — Diterima Jadi Karyawan';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 7;

    public ?string $companyId = null;
    public ?string $jobOpeningId = null;

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

    public function getCompanies(): \Illuminate\Support\Collection
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }

    public function getJobOpenings(): \Illuminate\Support\Collection
    {
        return $this->companyId
            ? JobOpening::where('company_id', $this->companyId)->latest()->get(['id', 'title'])
            : collect();
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
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat yang sudah resmi diterima jadi karyawan muncul di sini.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}
