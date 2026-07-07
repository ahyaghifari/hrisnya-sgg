<?php

namespace App\Services\Candidate;

use App\Jobs\SendRecruitmentEmailJob;
use App\Mail\RecruitmentMail;
use App\Models\Candidate\CandidateJobApplications;
use App\Models\Candidate\CandidateRecruitmentProcess;

class RecruitmentEmailService
{
    // Email: lamaran sedang diproses (SUBMITTED → UNDER_REVIEW)
    public function notifyApplicationUnderReview(CandidateJobApplications $application): void
    {
        $candidate    = $application->candidate;
        $position     = $application->jobVacancy?->jobPosition?->name ?? '-';
        $company      = $application->jobVacancy?->company?->name ?? '-';

        $subject = "[{$company}] Lamaran Anda Sedang Diproses – {$position}";

        $greeting = "Halo, {$candidate->name}";

        $bodyHtml = "
            <p>Terima kasih telah melamar posisi <strong>{$position}</strong> di <strong>{$company}</strong>.</p>
            <p>Kami dengan senang hati memberitahukan bahwa lamaran Anda sedang dalam proses review oleh tim rekrutmen kami.</p>
            <p>Kami akan segera menghubungi Anda mengenai tahapan selanjutnya. Mohon pantau email ini secara berkala.</p>
        ";

        $closingNote = "Jika Anda memiliki pertanyaan, jangan ragu untuk menghubungi tim rekrutmen kami.";

        $details = [
            'Posisi'     => $position,
            'Perusahaan' => $company,
            'Status'     => 'Sedang Diproses',
            'Tanggal'    => now()->translatedFormat('d F Y'),
        ];

        $this->dispatch($candidate->email, $subject, $candidate->name, $greeting, $bodyHtml, $closingNote, $details);
    }

    // Email: undangan tahapan rekrutmen baru (add_recruitment_process)
    public function notifyNewRecruitmentStage(
        CandidateJobApplications   $application,
        CandidateRecruitmentProcess $process,
    ): void {
        $candidate = $application->candidate;
        $position  = $application->jobVacancy?->jobPosition?->name ?? '-';
        $company   = $application->jobVacancy?->company?->name ?? '-';

        $processName    = $process->process_name ?? 'Tahapan Rekrutmen';
        $scheduledAt    = isset($process->metadata['scheduled_at'])
            ? \Carbon\Carbon::parse($process->metadata['scheduled_at'])->translatedFormat('d F Y')
            : null;
        $maximumAt      = isset($process->metadata['maximum_completion_at'])
            ? \Carbon\Carbon::parse($process->metadata['maximum_completion_at'])->translatedFormat('d F Y')
            : null;

        $subject = "[{$company}] Undangan Tahapan Rekrutmen: {$processName} – {$position}";

        $greeting = "Halo, {$candidate->name}";

        $scheduledLine  = $scheduledAt
            ? "<p>Tahapan ini dijadwalkan pada tanggal <strong>{$scheduledAt}</strong>.</p>"
            : '';
        $maximumLine    = $maximumAt
            ? "<p>Mohon selesaikan / konfirmasi kehadiran Anda sebelum tanggal <strong>{$maximumAt}</strong>.</p>"
            : '';
        $linkLine       = $process->link
            ? "<p>Tautan terkait: <a href=\"{$process->link}\">{$process->link}</a></p>"
            : '';
        $notesLine      = $process->notes
            ? "<p><em>Catatan dari tim rekrutmen:</em> {$process->notes}</p>"
            : '';

        $bodyHtml = "
            <p>Anda telah masuk pada tahapan selanjutnya dalam proses rekrutmen untuk posisi
            <strong>{$position}</strong> di <strong>{$company}</strong>.</p>
            <p>Tahapan yang perlu Anda ikuti: <strong>{$processName}</strong>.</p>
            {$scheduledLine}
            {$maximumLine}
            {$linkLine}
            {$notesLine}
            <p>Harap mempersiapkan diri dengan baik. Semoga berhasil!</p>
        ";

        $closingNote = "Jika ada pertanyaan atau kendala terkait jadwal, segera hubungi tim rekrutmen kami.";

        $details = [
            'Posisi'       => $position,
            'Perusahaan'   => $company,
            'Tahapan'      => $processName,
            'Jadwal'       => $scheduledAt ?? '-',
            'Batas Waktu'  => $maximumAt   ?? '-',
        ];

        $this->dispatch($candidate->email, $subject, $candidate->name, $greeting, $bodyHtml, $closingNote, $details);
    }

    // Email: kandidat diterima (accept_application)
    public function notifyApplicationAccepted(CandidateJobApplications $application): void
    {
        $candidate = $application->candidate;
        $position  = $application->jobVacancy?->jobPosition?->name ?? '-';
        $company   = $application->jobVacancy?->company?->name ?? '-';

        $subject = "[{$company}] Selamat! Anda Diterima – {$position}";

        $greeting = "Selamat, {$candidate->name}!";

        $bodyHtml = "
            <p>Kami dengan bangga menyampaikan bahwa Anda telah <strong>diterima</strong> sebagai karyawan baru
            untuk posisi <strong>{$position}</strong> di <strong>{$company}</strong>.</p>
            <p>Tim HR kami akan segera menghubungi Anda untuk informasi lebih lanjut mengenai
            proses onboarding, dokumen yang perlu disiapkan, dan jadwal hari pertama kerja Anda.</p>
            <p>Selamat bergabung bersama kami. Kami menantikan kontribusi Anda!</p>
        ";

        $closingNote = "Jika ada pertanyaan, silakan hubungi tim HR kami secepatnya.";

        $details = [
            'Posisi'     => $position,
            'Perusahaan' => $company,
            'Status'     => '✅ Diterima',
            'Tanggal'    => now()->translatedFormat('d F Y'),
        ];

        $this->dispatch($candidate->email, $subject, $candidate->name, $greeting, $bodyHtml, $closingNote, $details);
    }

    // Email: kandidat ditolak (reject_application)
    public function notifyApplicationRejected(CandidateJobApplications $application): void
    {
        $candidate = $application->candidate;
        $position  = $application->jobVacancy?->jobPosition?->name ?? '-';
        $company   = $application->jobVacancy?->company?->name ?? '-';

        $subject = "[{$company}] Pemberitahuan Hasil Seleksi – {$position}";

        $greeting = "Halo, {$candidate->name}";

        $bodyHtml = "
            <p>Terima kasih atas minat dan waktu yang telah Anda luangkan untuk melamar posisi
            <strong>{$position}</strong> di <strong>{$company}</strong>.</p>
            <p>Setelah melalui proses seleksi yang ketat, dengan berat hati kami menyampaikan bahwa kami
            <strong>belum dapat melanjutkan proses rekrutmen</strong> Anda untuk posisi ini.</p>
            <p>Keputusan ini tidak mengurangi penilaian kami terhadap kompetensi dan potensi Anda.
            Kami mendorong Anda untuk terus mengembangkan diri dan membuka peluang di masa mendatang.</p>
        ";

        $closingNote = "Kami mengucapkan terima kasih atas partisipasi Anda dan berharap yang terbaik untuk karir Anda ke depan.";

        $details = [
            'Posisi'     => $position,
            'Perusahaan' => $company,
            'Status'     => 'Belum Berhasil',
            'Tanggal'    => now()->translatedFormat('d F Y'),
        ];

        $this->dispatch($candidate->email, $subject, $candidate->name, $greeting, $bodyHtml, $closingNote, $details);
    }

    // Buat Mailable dan push ke queue
    protected function dispatch(
        string $recipientEmail,
        string $subject,
        string $recipientName,
        string $greeting,
        string $bodyHtml,
        string $closingNote = '',
        array  $details     = [],
    ): void {
        $mail = new RecruitmentMail(
            mailSubject:   $subject,
            recipientName: $recipientName,
            greeting:      $greeting,
            bodyHtml:      $bodyHtml,
            closingNote:   $closingNote,
            details:       $details,
        );

        SendRecruitmentEmailJob::dispatch($recipientEmail, $mail);
    }
}
