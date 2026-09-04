<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use App\Models\Rekrutmen\JobOpeningStageLog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
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
 */
class OfferingLetterPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.rekrutmen.pages.offering-letter-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Offering Letter';

    protected static ?string $title = 'Offering Letter';

    protected static string|UnitEnum|null $navigationGroup = 'Kandidat';

    protected static ?int $navigationSort = 3;

    public ?string $companyId = null;
    public ?string $jobOpeningId = null;

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

    public function unduhSuratMasukAction(): Action
    {
        return Action::make('unduhSuratMasuk')
            ->label('Lihat Surat')
            ->icon('heroicon-m-eye')
            ->color('gray')
            ->visible(fn ($record) => (bool) $record->offering_letter_path)
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
            ->visible(fn ($record) => $record->offeringNegotiations->isNotEmpty())
            ->modalHeading('Negosiasi Offering Letter')
            ->modalSubmitActionLabel('Kirim Balasan')
            ->schema(fn ($record) => [
                Placeholder::make('riwayat')->label('Riwayat')->content(new HtmlString(
                    collect($record->offeringNegotiations)->map(fn ($n) =>
                        '<div style="margin-bottom:8px;padding:8px 10px;border-radius:8px;background:'
                        . ($n->sender === 'applicant' ? '#f1f5f9' : '#eef2ff') . '"><strong>'
                        . ($n->sender === 'applicant' ? 'Kandidat' : 'Rekruter') . '</strong> <span style="color:#94a3b8">· '
                        . $n->created_at?->format('d M Y, H:i') . '</span><br>' . nl2br(e($n->message)) . '</div>'
                    )->implode('')
                )),
                Textarea::make('message')->label('Balasan')->rows(3)->required()->maxLength(2000),
            ])
            ->action(function (array $data, $record): void {
                $record->offeringNegotiations()->create(['sender' => 'recruiter', 'message' => $data['message']]);
                Notification::make()->title('Balasan negosiasi terkirim')->success()->send();
            });
    }

    public function lanjutkanAction(): Action
    {
        return Action::make('lanjutkan')
            ->label(fn ($record) => ($record->jobOpening->company?->requires_mcu ?? true) ? 'Lanjut ke MCU' : 'Lanjut ke Onboarding')
            ->icon('heroicon-m-arrow-right-circle')
            ->color('success')
            ->visible(fn ($record) => $this->canDecide() && $record->offering_letter_signed_path !== null)
            ->requiresConfirmation()
            ->modalDescription('Pastikan surat yang diunggah kandidat sudah benar sebelum melanjutkan.')
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
            ])
            ->recordActions([
                $this->uploadSuratAction(),
                $this->unduhSuratMasukAction(),
                $this->negosiasiAction(),
                $this->unduhSuratSignedAction(),
                $this->lanjutkanAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Pilih perusahaan & lowongan')
            ->emptyStateDescription('Kandidat lulus wawancara muncul di sini untuk diberi offering letter.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
