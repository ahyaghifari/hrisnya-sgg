<?php

namespace App\Services\Candidate;

use App\Enum\Recruitment\ProcessStatus;
use App\Enum\Recruitment\RecruitmentProcessStatus;
use App\Models\Candidate\CandidateJobApplications;
use App\Models\Candidate\CandidateRecruitmentProcess;
use App\Models\Role;
use Illuminate\Support\Facades\Storage;

/**
 * Logic proses lamaran kandidat (approval direktur, tahapan rekrutmen, skor, MCU, terima jadi
 * karyawan) — porting dari Filament `ViewCandidateApplicant`, dipakai portal. Efek domain harus
 * identik dengan admin panel (email notifikasi di titik yang sama, sync ke User via
 * CandidateService tetap dipakai apa adanya).
 */
class CandidateApplicationService
{
    public function __construct(
        protected CandidateService $candidateService,
        protected RecruitmentEmailService $emailService,
    ) {}

    public function processRecruitment(CandidateJobApplications $application): void
    {
        $application->update(['status' => ProcessStatus::UNDER_REVIEW->value]);

        $this->emailService->notifyApplicationUnderReview($application);
    }

    public function addRecruitmentProcess(CandidateJobApplications $application, array $data, array $attachmentPaths): CandidateRecruitmentProcess
    {
        $process = $application->recruitmentProcesses()->create([
            'process_name' => $data['process_name'] ?? null,
            'process_type' => $data['process_type'] ?? null,
            'link' => $data['link'] ?? null,
            'notes' => $data['notes'] ?? null,
            'attachments' => $attachmentPaths,
            'metadata' => $data['metadata'] ?? null,
            'status' => RecruitmentProcessStatus::ASSIGNED->value,
        ]);

        $application->update([
            'status' => ProcessStatus::IN_SELECTION->value,
            'current_stage' => $data['process_name'] ?? null,
        ]);

        $this->emailService->notifyNewRecruitmentStage($application, $process);

        return $process;
    }

    public function addScore(CandidateRecruitmentProcess $process, array $data): void
    {
        $process->update([
            'score' => $data['score'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'status' => $data['status'] ?? RecruitmentProcessStatus::COMPLETED->value,
        ]);
    }

    public function confirmReschedule(CandidateRecruitmentProcess $process): void
    {
        $metadata = $process->metadata ?? [];
        $metadata['scheduled_at'] = $metadata['rescheduled_at'] ?? null;
        $metadata['reschedule_notes'] = 'Penjadwalan ulang dikonfirmasi.';

        $process->update([
            'status' => RecruitmentProcessStatus::RESCHEDULED->value,
            'metadata' => $metadata,
        ]);
    }

    public function markMcuCompleted(CandidateRecruitmentProcess $process, array $data): void
    {
        $metadata = $process->metadata ?? [];
        $metadata['results_summary'] = $data['results_summary'] ?? null;
        $metadata['mcu_attachments'] = $data['attachments'] ?? null;
        $metadata['submitted_at'] = now();

        $process->update([
            'status' => RecruitmentProcessStatus::COMPLETED->value,
            'metadata' => $metadata,
        ]);
    }

    /** Hapus proses + file lampiran terkait — dijaga status ASSIGNED di sini juga (defense-in-depth,
     * bukan cuma disembunyikan di UI). */
    public function deleteProcess(CandidateRecruitmentProcess $process): void
    {
        abort_unless($process->status === RecruitmentProcessStatus::ASSIGNED->value, 403);

        foreach ($process->attachments ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $process->delete();
    }

    public function requestApproval(CandidateJobApplications $application, ?string $notes): void
    {
        $metadata = $application->metadata ?? [];
        $metadata['approval_notes'] = $notes;

        $application->update([
            'approval_status' => 'requested',
            'metadata' => $metadata,
        ]);
    }

    public function approveCandidate(CandidateJobApplications $application, ?string $notes, string $actorId): void
    {
        $metadata = $application->metadata ?? [];
        $metadata['director_approval_notes'] = $notes;

        $application->update([
            'approval_status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function rejectCandidate(CandidateJobApplications $application, ?string $notes, string $actorId): void
    {
        $metadata = $application->metadata ?? [];
        $metadata['director_rejection_notes'] = $notes;

        $application->update([
            'approval_status' => 'rejected',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function rejectApplication(CandidateJobApplications $application): void
    {
        $application->update([
            'status' => ProcessStatus::REJECTED->value,
            'finished_at' => now(),
        ]);

        $this->emailService->notifyApplicationRejected($application);
    }

    /** Terima kandidat — sync ke User karyawan (CandidateService), lalu tandai HIRED. */
    public function acceptApplication(CandidateJobApplications $application, array $data): void
    {
        $this->candidateService->syncDataCandidateToUser($application->id, [
            'nip' => $data['nip'],
            'type_nakes' => $data['type_nakes'],
            'company_id' => $application->jobVacancy->company_id,
            'employee_type_id' => $data['employee_type_id'],
            'role_id' => $data['role_id'],
            'joined_at' => $data['joined_at'],
        ]);

        $application->update([
            'status' => ProcessStatus::HIRED->value,
            'current_stage' => 'Kandidat Diterima',
            'finished_at' => now(),
        ]);

        $this->emailService->notifyApplicationAccepted($application);
    }
}
