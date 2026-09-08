<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Models\Rekrutmen\JobOpeningStageLog;
use App\Models\Rekrutmen\ScreeningDecision;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
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

/**
 * Daftar Pelamar — port dari ApplicantsPage project `rekrutmen` (FL3 -> FL4), baca-tulis
 * DB `recruitment` langsung (bukan API). Selektor + statistik + tabel + aksi bulk ke
 * screening + seleksi berkas (draft -> terapkan). Keputusan seleksi bikin draft email
 * (lolos/ditolak); admin pilih baris lalu "Kirim Email Pemberitahuan".
 */
class ApplicantsPage extends Page implements HasActions, HasForms, HasTable
{
    use \App\Filament\Rekrutmen\Concerns\ScopedToCompany;
    use \App\Filament\Rekrutmen\Concerns\SendsApplicantNotifications;
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.applicants-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Daftar Pelamar';

    protected static ?string $title = 'Daftar Pelamar';

    // Tanpa grup — item navigasi mandiri di paling atas.
    protected static ?int $navigationSort = 2;

    public ?string $companyId = null;
    public ?string $jobOpeningId = null;
    public ?string $statusFilter = null;

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

    public function updatedStatusFilter(): void
    {
        $this->resetTable();
    }

    public function getJobOpenings(): \Illuminate\Support\Collection
    {
        if (! $this->companyId) {
            return collect();
        }

        return JobOpening::where('company_id', $this->companyId)->latest()->get(['id', 'title']);
    }

    public function getAppliedCount(): int
    {
        return $this->jobOpeningId
            ? JobApplication::where('job_opening_id', $this->jobOpeningId)->where('status', 'applied')->count()
            : 0;
    }

    public function getScreeningCount(): int
    {
        return $this->jobOpeningId
            ? JobApplication::where('job_opening_id', $this->jobOpeningId)->where('status', 'screening')->count()
            : 0;
    }

    public function getDraftDecisionCount(): int
    {
        return $this->jobOpeningId
            ? ScreeningDecision::whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $this->jobOpeningId))->count()
            : 0;
    }

    /** @return list<array{label:string,value:int,color:string}> */
    public function getStats(): array
    {
        if (! $this->jobOpeningId) {
            return [];
        }

        $base = fn () => JobApplication::where('job_opening_id', $this->jobOpeningId);

        return [
            ['label' => 'Total Pelamar', 'value' => $base()->count(), 'color' => '#042c6c'],
            ['label' => 'Lamaran Masuk', 'value' => $base()->where('status', 'applied')->count(), 'color' => '#64748b'],
            ['label' => 'Screening', 'value' => $base()->where('status', 'screening')->count(), 'color' => '#0284c7'],
            ['label' => 'Lolos Berkas', 'value' => $base()->where('status', 'shortlisted')->count(), 'color' => '#047C54'],
            ['label' => 'Tidak Dilanjutkan', 'value' => $base()->where('status', 'rejected')->count(), 'color' => '#dc2626'],
        ];
    }

    public function bulkMoveToScreeningAction(): Action
    {
        $count = $this->getAppliedCount();

        return Action::make('bulkMoveToScreening')
            ->label("Mulai Peninjauan Berkas ({$count})")
            ->icon('heroicon-m-arrow-right')
            ->color('info')
            ->visible(fn () => $this->canDecide() && $count > 0)
            ->requiresConfirmation()
            ->modalHeading('Mulai Peninjauan Berkas')
            ->modalDescription("Memindahkan {$count} lamaran dari Lamaran Masuk ke Peninjauan Berkas.")
            ->modalSubmitActionLabel("Ya, Pindahkan {$count}")
            ->action(function (): void {
                if (! $this->jobOpeningId) {
                    return;
                }

                $apps = JobApplication::where('job_opening_id', $this->jobOpeningId)->where('status', 'applied')->get();
                foreach ($apps as $app) {
                    $app->update(['status' => JobApplication::STATUS_SCREENING]);
                }
                JobOpeningStageLog::record($this->jobOpeningId, 'applied', 'screening', $apps->count());

                $this->resetTable();
                Notification::make()->title("{$apps->count()} lamaran dipindahkan ke Peninjauan Berkas")->success()->send();
            });
    }

    public function seleksiBerkasAction(): Action
    {
        $options = $this->screeningOptions();
        $existingDraft = $this->jobOpeningId
            ? ScreeningDecision::whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $this->jobOpeningId))
                ->where('decision', 'passed')->pluck('job_application_id')->all()
            : [];

        return Action::make('seleksiBerkas')
            ->label(count($options) > 0 ? 'Seleksi Berkas (' . count($options) . ' pelamar)' : 'Seleksi Berkas')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('warning')
            ->visible(fn () => $this->canDecide() && count($options) > 0)
            ->fillForm(['passed_ids' => $existingDraft])
            ->schema([
                CheckboxList::make('passed_ids')
                    ->label('Centang pelamar yang LOLOS seleksi berkas')
                    ->helperText('Yang tidak dicentang = tetap di Peninjauan Berkas. Status baru berubah setelah "Terapkan Keputusan".')
                    ->options($options)
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(1),
            ])
            ->modalHeading('Seleksi Berkas — Pilih yang Lolos')
            ->modalSubmitActionLabel('Simpan Draft Keputusan')
            ->action(function (array $data): void {
                if (! $this->jobOpeningId) {
                    return;
                }

                $passedIds = $data['passed_ids'] ?? [];
                $allIds = JobApplication::where('job_opening_id', $this->jobOpeningId)->where('status', 'screening')->pluck('id')->all();

                ScreeningDecision::saveDraftForOpening($this->jobOpeningId, $passedIds, $allIds);

                $passed = count($passedIds);
                Notification::make()
                    ->title("Draft tersimpan: {$passed} lolos, " . (count($allIds) - $passed) . ' tidak lolos. Belum diterapkan.')
                    ->warning()->send();
            });
    }

    public function publishScreeningAction(): Action
    {
        $draftCount = $this->getDraftDecisionCount();

        return Action::make('publishScreening')
            ->label("Terapkan Keputusan ({$draftCount})")
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn () => $this->canDecide() && $draftCount > 0)
            ->requiresConfirmation()
            ->modalHeading('Terapkan Keputusan Seleksi Berkas?')
            ->modalDescription('Yang lolos → status "Lolos Seleksi Berkas". Yang tidak → tetap di Peninjauan Berkas. Draft email dibuat — kirim lewat "Kirim Email Pemberitahuan".')
            ->modalSubmitActionLabel('Terapkan')
            ->action(function (): void {
                if (! $this->jobOpeningId) {
                    return;
                }

                [$passed, $rejected] = ScreeningDecision::applyForOpening($this->jobOpeningId);

                $this->resetTable();
                Notification::make()
                    ->title("{$passed} lolos. {$rejected} tetap di Peninjauan Berkas — draft email siap dikirim lewat \"Kirim Email Pemberitahuan\".")
                    ->success()->send();
            });
    }

    /** @return array<string,string> job_application_id => nama */
    private function screeningOptions(): array
    {
        if (! $this->jobOpeningId) {
            return [];
        }

        return JobApplication::with(['answers' => fn ($q) => $q->where('field_key', 'full_name')])
            ->where('job_opening_id', $this->jobOpeningId)
            ->where('status', 'screening')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->answers->firstWhere('field_key', 'full_name')?->value ?? '(tanpa nama)'])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobApplication::query()
                    ->with(['answers' => fn ($q) => $q->whereIn('field_key', ['full_name', 'gender', 'education_level'])])
                    ->when(
                        $this->jobOpeningId,
                        fn (Builder $q) => $q->where('job_opening_id', $this->jobOpeningId),
                        fn (Builder $q) => $q->whereRaw('1 = 0'),
                    )
                    ->when($this->statusFilter, fn (Builder $q) => $q->where('status', $this->statusFilter))
                    ->latest()
            )
            ->columns([
                TextColumn::make('no')->label('No')->rowIndex(),
                TextColumn::make('full_name')->label('Nama Lengkap')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'full_name')?->value ?? '—'),
                TextColumn::make('gender')->label('Jenis Kelamin')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'gender')?->value ?? '—'),
                TextColumn::make('education_level')->label('Pendidikan')
                    ->state(fn ($record) => $record->answers->firstWhere('field_key', 'education_level')?->value ?? '—'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'screening', 'interview' => 'info',
                        'shortlisted', 'offering', 'mcu', 'onboarding' => 'warning',
                        'passed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'applied' => 'Lamaran Masuk', 'screening' => 'Peninjauan Berkas',
                        'shortlisted' => 'Lolos Seleksi Berkas', 'interview' => 'Wawancara',
                        'offering' => 'Offering', 'mcu' => 'MCU', 'onboarding' => 'TTD & Onboarding',
                        'passed' => 'Diterima', 'rejected' => 'Tidak Dilanjutkan', default => $state,
                    }),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn ($record) => \App\Filament\Rekrutmen\Pages\ApplicantDetailPage::getUrl() . '?application=' . $record->id),
            ])
            ->toolbarActions([$this->kirimNotifBulkAction()])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Pilih perusahaan lalu lowongan di atas.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
