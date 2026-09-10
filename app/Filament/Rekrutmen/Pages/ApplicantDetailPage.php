<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use App\Models\Rekrutmen\JobApplication;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Detail 1 pelamar — port dari ApplicantDetailPage project `rekrutmen` (FL3 -> FL4), DB
 * `recruitment` langsung. Diakses `?application=<id>`, tidak muncul di nav.
 *
 * Info lamaran + jawaban form per seksi + riwayat status + aksi "Tidak Dilanjutkan"
 * (status -> rejected + draft notif). Pengiriman email pemberitahuan yang ditahan
 * dilakukan lewat aksi bulk "Kirim Email Pemberitahuan" di halaman tabel/pipeline,
 * bukan dari sini. Data diri kandidat (9 tabel anak) tampil begitu ada (partial
 * candidate-profile-view). Unduh berkas dokumen kandidat masih 501 (Fase 6b lanjutan).
 */
class ApplicantDetailPage extends Page implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected string $view = 'filament.rekrutmen.pages.applicant-detail-page';

    protected static bool $shouldRegisterNavigation = false;

    public ?JobApplication $application = null;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('View:JobOpenings');
    }

    public function mount(): void
    {
        $id = request('application');

        $this->application = $id
            ? JobApplication::with([
                'jobOpening.company.formSections.fields',
                'answers',
                'statusLogs',
                'candidate.familyMembers',
                'candidate.educations',
                'candidate.trainings',
                'candidate.organizations',
                'candidate.workExperiences',
                'candidate.references',
                'candidate.additionalInfo',
                'candidate.selfAssessment',
                'candidate.documents',
                'candidate.agreements.companyAgreement',
                'candidate.agreements.jobOpeningAgreement',
            ])->find($id)
            : null;

        abort_if(! $this->application, 404);
    }

    public function getTitle(): string
    {
        return 'Detail Pelamar — ' . ($this->application?->answersMap()['full_name'] ?? '');
    }

    private function canDecide(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('Update:JobOpenings');
    }

    public function changeStatus(string $status, ?string $reason = null): void
    {
        abort_unless($this->canDecide(), 403);
        if (! $this->application) {
            return;
        }

        $allowed = ['applied', 'screening', 'shortlisted', 'interview', 'passed', 'rejected'];
        if (! in_array($status, $allowed, true)) {
            return;
        }

        $prev = $this->application->status;
        $this->application->update(['status' => $status]);

        if ($status === 'rejected' && $reason && trim($reason) !== '') {
            $this->application->statusLogs()->latest('changed_at')->first()?->update(['notes' => trim($reason)]);
        }

        $event = match (true) {
            $status === 'shortlisted' => CompanyMessageTemplate::EVENT_SCREENING_PASSED,
            $status === 'rejected' && $prev === 'screening' => CompanyMessageTemplate::EVENT_SCREENING_REJECTED,
            $status === 'rejected' => CompanyMessageTemplate::EVENT_MANUAL_REJECTED,
            default => null,
        };

        if ($event) {
            ApplicationNotification::createDraftFor($this->application, $event, ['notes' => $reason]);
        }

        Notification::make()->title('Status diperbarui')->success()->send();
        $this->redirect(request()->fullUrl());
    }

    public function tidakDilanjutkanAction(): Action
    {
        return Action::make('tidakDilanjutkan')
            ->label('Tidak Dilanjutkan')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->visible(fn () => $this->canDecide()
                && $this->application
                && ! in_array($this->application->status, ['rejected', 'passed'], true))
            ->modalHeading('Tandai Tidak Dilanjutkan')
            ->modalDescription('Untuk kasus di luar alur normal — mis. kandidat mengundurkan diri, bukan gagal di salah satu tahap.')
            ->modalSubmitActionLabel('Tidak Dilanjutkan')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan Tidak Dilanjutkan')
                    ->placeholder('Mis. Kandidat mengundurkan diri karena sudah diterima di tempat lain.')
                    ->rows(3)
                    ->required(),
            ])
            ->action(fn (array $data) => $this->changeStatus('rejected', $data['reason']));
    }
}
