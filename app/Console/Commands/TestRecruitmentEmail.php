<?php

namespace App\Console\Commands;

use App\Jobs\SendRecruitmentEmailJob;
use App\Mail\RecruitmentMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestRecruitmentEmail extends Command
{
    protected $signature = 'recruitment:test-email
                            {--email=recruitment-test@yopmail.com : Alamat tujuan}
                            {--type=all : Tipe: all | under_review | new_stage | accepted | rejected}
                            {--queue : Kirim lewat queue (default: sync/langsung)}';

    protected $description = 'Kirim email rekrutmen percobaan ke alamat email tertentu (default: yopmail)';

    // Dummy data yang dipakai di semua tipe email
    private array $dummy = [
        'candidateName' => 'Budi Santoso',
        'position'      => 'Software Engineer',
        'company'       => 'PT Syifa Medika',
    ];

    public function handle(): int
    {
        $email   = $this->option('email');
        $type    = $this->option('type');
        $useQueue = $this->option('queue');

        $this->newLine();
        $this->line("  <fg=cyan>Tujuan email :</> {$email}");
        $this->line("  <fg=cyan>Tipe          :</> {$type}");
        $this->line("  <fg=cyan>Mode          :</> " . ($useQueue ? 'queue' : 'sync (langsung)'));
        $this->newLine();

        $types = $type === 'all'
            ? ['under_review', 'new_stage', 'accepted', 'rejected']
            : [$type];

        $valid = ['under_review', 'new_stage', 'accepted', 'rejected'];
        foreach ($types as $t) {
            if (!in_array($t, $valid, true)) {
                $this->error("Tipe tidak valid: '{$t}'. Pilihan: " . implode(', ', $valid));
                return Command::FAILURE;
            }
        }

        foreach ($types as $t) {
            $mailable = $this->buildMailable($t);

            try {
                if ($useQueue) {
                    SendRecruitmentEmailJob::dispatch($email, $mailable);
                    $this->line("  <fg=yellow>[QUEUED]</> {$t}");
                } else {
                    Mail::to($email)->send($mailable);
                    $this->line("  <fg=green>[SENT]</> {$t}");
                }
            } catch (\Throwable $e) {
                $this->line("  <fg=red>[FAIL]</> {$t} → {$e->getMessage()}");
                $this->newLine();
                return Command::FAILURE;
            }
        }

        $this->newLine();

        if ($useQueue) {
            $this->info('Email telah dimasukkan ke dalam queue. Jalankan `php artisan queue:work` jika belum berjalan.');
        } else {
            $this->info('Email berhasil dikirim!');
            if (str_contains($email, '@yopmail.com')) {
                $inbox = 'https://yopmail.com/en/?login=' . rawurlencode(explode('@', $email)[0]);
                $this->line("  Cek inbox di: <fg=blue;href={$inbox}>{$inbox}</>");
            }
        }

        $this->newLine();
        return Command::SUCCESS;
    }

    private function buildMailable(string $type): RecruitmentMail
    {
        return match ($type) {
            'under_review' => $this->buildUnderReview(),
            'new_stage'    => $this->buildNewStage(),
            'accepted'     => $this->buildAccepted(),
            'rejected'     => $this->buildRejected(),
        };
    }

    private function buildUnderReview(): RecruitmentMail
    {
        ['candidateName' => $name, 'position' => $pos, 'company' => $co] = $this->dummy;

        return new RecruitmentMail(
            subject:       "[{$co}] Lamaran Anda Sedang Diproses – {$pos}",
            recipientName: $name,
            greeting:      "Halo, {$name}",
            bodyHtml: "
                <p>Terima kasih telah melamar posisi <strong>{$pos}</strong> di <strong>{$co}</strong>.</p>
                <p>Lamaran Anda kini sedang dalam proses review oleh tim rekrutmen kami.</p>
                <p>Kami akan segera menghubungi Anda mengenai tahapan selanjutnya.</p>
            ",
            closingNote:   "Jika ada pertanyaan, jangan ragu menghubungi tim rekrutmen kami.",
            details: [
                'Posisi'     => $pos,
                'Perusahaan' => $co,
                'Status'     => 'Sedang Diproses',
                'Tanggal'    => now()->translatedFormat('d F Y'),
            ],
        );
    }

    private function buildNewStage(): RecruitmentMail
    {
        ['candidateName' => $name, 'position' => $pos, 'company' => $co] = $this->dummy;
        $stage    = 'Wawancara HRD';
        $schedule = now()->addDays(3)->translatedFormat('d F Y');
        $deadline = now()->addDays(5)->translatedFormat('d F Y');

        return new RecruitmentMail(
            subject:       "[{$co}] Undangan Tahapan Rekrutmen: {$stage} – {$pos}",
            recipientName: $name,
            greeting:      "Halo, {$name}",
            bodyHtml: "
                <p>Anda telah masuk ke tahapan selanjutnya dalam proses rekrutmen untuk posisi
                <strong>{$pos}</strong> di <strong>{$co}</strong>.</p>
                <p>Tahapan berikutnya: <strong>{$stage}</strong>.</p>
                <p>Tahapan ini dijadwalkan pada tanggal <strong>{$schedule}</strong>.</p>
                <p>Mohon konfirmasi kehadiran sebelum <strong>{$deadline}</strong>.</p>
                <p><em>Catatan dari tim rekrutmen:</em> Harap membawa CV terbaru dan dokumen pendukung.</p>
            ",
            closingNote:   "Jika ada kendala terkait jadwal, segera hubungi tim rekrutmen kami.",
            details: [
                'Posisi'      => $pos,
                'Perusahaan'  => $co,
                'Tahapan'     => $stage,
                'Jadwal'      => $schedule,
                'Batas Waktu' => $deadline,
            ],
        );
    }

    private function buildAccepted(): RecruitmentMail
    {
        ['candidateName' => $name, 'position' => $pos, 'company' => $co] = $this->dummy;

        return new RecruitmentMail(
            subject:       "[{$co}] Selamat! Anda Diterima – {$pos}",
            recipientName: $name,
            greeting:      "Selamat, {$name}!",
            bodyHtml: "
                <p>Kami dengan bangga menyampaikan bahwa Anda telah <strong>diterima</strong>
                sebagai karyawan baru untuk posisi <strong>{$pos}</strong> di <strong>{$co}</strong>.</p>
                <p>Tim HR kami akan segera menghubungi Anda untuk informasi onboarding, dokumen
                yang perlu disiapkan, dan jadwal hari pertama kerja.</p>
                <p>Selamat bergabung bersama kami!</p>
            ",
            closingNote:   "Jika ada pertanyaan, silakan hubungi tim HR kami.",
            details: [
                'Posisi'     => $pos,
                'Perusahaan' => $co,
                'Status'     => '✅ Diterima',
                'Tanggal'    => now()->translatedFormat('d F Y'),
            ],
        );
    }

    private function buildRejected(): RecruitmentMail
    {
        ['candidateName' => $name, 'position' => $pos, 'company' => $co] = $this->dummy;

        return new RecruitmentMail(
            subject:       "[{$co}] Pemberitahuan Hasil Seleksi – {$pos}",
            recipientName: $name,
            greeting:      "Halo, {$name}",
            bodyHtml: "
                <p>Terima kasih atas minat dan waktu yang telah Anda luangkan untuk melamar posisi
                <strong>{$pos}</strong> di <strong>{$co}</strong>.</p>
                <p>Setelah melalui proses seleksi yang ketat, dengan berat hati kami menyampaikan
                bahwa kami <strong>belum dapat melanjutkan proses rekrutmen</strong> Anda untuk posisi ini.</p>
                <p>Kami mendorong Anda untuk terus berkembang dan semoga sukses di kesempatan berikutnya.</p>
            ",
            closingNote:   "Terima kasih atas partisipasi Anda. Semoga yang terbaik untuk karir Anda.",
            details: [
                'Posisi'     => $pos,
                'Perusahaan' => $co,
                'Status'     => 'Belum Berhasil',
                'Tanggal'    => now()->translatedFormat('d F Y'),
            ],
        );
    }
}
