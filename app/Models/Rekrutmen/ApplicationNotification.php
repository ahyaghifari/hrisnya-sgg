<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Draft/antrean email pemberitahuan ke pelamar (DB rekrutmen_db, dibagi). HRIS TIDAK
 * mengirim email — cuma menulis baris:
 *   - status 'draft'  = ditahan (penolakan tahap ini) sampai admin klik "Kirim Email
 *     Pemberitahuan" yang mengubahnya jadi 'queued'
 *   - status 'queued' = siap dikirim (event positif langsung ke sini)
 *   - status 'sent'   = sudah dikirim
 * Pengiriman aktual dilakukan project `rekrutmen` lewat command terjadwal
 * `notifications:dispatch` yang memproses baris 'queued'.
 */
class ApplicationNotification extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['job_application_id', 'event', 'subject', 'body', 'status', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Tombol CTA di email untuk event yang butuh aksi pelamar. Null = tidak ada tombol. */
    public function cta(): ?array
    {
        $base = rtrim((string) config('services.rekrutmen.url'), '/');
        if ($base === '') {
            return null;
        }

        return match ($this->event) {
            CompanyMessageTemplate::EVENT_QUIZ_SENT => ['url' => "{$base}/tes-online", 'label' => 'Kerjakan Tes'],
            CompanyMessageTemplate::EVENT_OFFERING_LETTER_READY => ['url' => "{$base}/dashboard", 'label' => 'Unggah Surat Penawaran'],
            CompanyMessageTemplate::EVENT_INTERVIEW_SCHEDULED => ['url' => "{$base}/lamaran/{$this->job_application_id}", 'label' => 'Lihat Jadwal Wawancara'],
            CompanyMessageTemplate::EVENT_MCU_SCHEDULED => ['url' => "{$base}/lamaran/{$this->job_application_id}", 'label' => 'Lihat Jadwal MCU'],
            CompanyMessageTemplate::EVENT_OFFERING_NEGOTIATION_REPLIED => ['url' => "{$base}/dashboard", 'label' => 'Lihat Balasan Negosiasi'],
            CompanyMessageTemplate::EVENT_INTERVIEW_RESCHEDULED => ['url' => "{$base}/lamaran/{$this->job_application_id}", 'label' => 'Lihat Jadwal Wawancara'],
            CompanyMessageTemplate::EVENT_MCU_RESCHEDULED => ['url' => "{$base}/lamaran/{$this->job_application_id}", 'label' => 'Lihat Jadwal MCU'],
            default => null,
        };
    }

    /**
     * Antrekan email perubahan jadwal (wawancara/MCU). Disimpan sebagai baris 'queued' —
     * project rekrutmen yang mengirim. Boleh berulang (tak ada dedup). $kind = 'interview'|'mcu'.
     */
    public static function notifyReschedule(JobApplication $app, string $kind, array $data): void
    {
        $app->loadMissing('jobOpening', 'user');

        if (! $app->user?->email) {
            return;
        }

        $name = $app->answersMap()['full_name'] ?? 'Pelamar';
        $jobTitle = $app->jobOpening->title ?? '';
        $label = $kind === 'mcu' ? 'Medical Check Up' : 'Wawancara';
        $event = $kind === 'mcu'
            ? CompanyMessageTemplate::EVENT_MCU_RESCHEDULED
            : CompanyMessageTemplate::EVENT_INTERVIEW_RESCHEDULED;

        $detail = "Tanggal: " . ($data['date'] ?? '-')
            . "\nWaktu: " . ($data['time'] ?? '-')
            . "\nLokasi: " . ($data['location'] ?? '-')
            . (($data['notes'] ?? '') !== '' ? "\nCatatan: " . $data['notes'] : '');

        static::create([
            'job_application_id' => $app->id,
            'event' => $event,
            'subject' => "Perubahan Jadwal {$label} – {$jobTitle}",
            'body' => "Halo {$name},\n\nJadwal {$label} kamu untuk posisi {$jobTitle} telah diperbarui. Berikut jadwal terbaru:\n\n{$detail}\n\nMohon perhatikan perubahan ini. Sampai jumpa di jadwal yang baru.\n\nSalam,\nTim Rekrutmen",
            'status' => 'queued',
        ]);
    }

    /**
     * Antrekan email balasan negosiasi offering. Disimpan sebagai baris 'queued' —
     * project rekrutmen yang mengirim. Boleh berulang (riwayat di `offering_negotiations`).
     */
    public static function notifyNegotiationReply(JobApplication $app, string $replyMessage): void
    {
        $app->loadMissing('jobOpening', 'user');

        if (! $app->user?->email) {
            return;
        }

        $name = $app->answersMap()['full_name'] ?? 'Pelamar';
        $jobTitle = $app->jobOpening->title ?? '';

        static::create([
            'job_application_id' => $app->id,
            'event' => CompanyMessageTemplate::EVENT_OFFERING_NEGOTIATION_REPLIED,
            'subject' => "Balasan Negosiasi Surat Penawaran – {$jobTitle}",
            'body' => "Halo {$name},\n\nTim rekrutmen telah membalas pengajuan negosiasimu untuk posisi {$jobTitle}:\n\n\"{$replyMessage}\"\n\nSilakan buka dashboard untuk menanggapi atau menandatangani surat penawaran.\n\nSalam,\nTim Rekrutmen",
            'status' => 'queued',
        ]);
    }

    /** Tandai draft ini siap dikirim (status 'queued'). Pengiriman aktual oleh project
     *  rekrutmen (command notifications:dispatch). False kalau bukan draft. */
    public function send(): bool
    {
        if ($this->status !== 'draft') {
            return false;
        }

        $this->update(['status' => 'queued']);

        return true;
    }

    /** Bikin draft lalu langsung antrekan — buat event "positif" yang tidak perlu ditahan admin. */
    public static function createAndSend(JobApplication $app, string $event, array $extra = []): void
    {
        static::createDraftFor($app, $event, $extra);

        static::where('job_application_id', $app->id)
            ->where('event', $event)
            ->where('status', 'draft')
            ->update(['status' => 'queued']);
    }

    /** Antrekan semua draft milik daftar lamaran ini (opsional filter event). @return int jumlah diantrekan. */
    public static function flushDraftsFor(array $applicationIds, ?string $event = null): int
    {
        if (! $applicationIds) {
            return 0;
        }

        return static::whereIn('job_application_id', $applicationIds)
            ->where('status', 'draft')
            ->when($event, fn ($q) => $q->where('event', $event))
            ->update(['status' => 'queued']);
    }

    /** Bikin draft notifikasi (tanpa kirim). Null kalau draft event itu sudah ada. */
    public static function createDraftFor(JobApplication $app, string $event, array $extra = []): ?static
    {
        if (static::where('job_application_id', $app->id)->where('event', $event)->exists()) {
            return null;
        }

        $app->loadMissing('jobOpening');
        $companyId = $app->jobOpening->company_id;
        $template = CompanyMessageTemplate::forCompanyEvent($companyId, $event);
        $applicantName = $app->answersMap()['full_name'] ?? 'Pelamar';
        $jobTitle = $app->jobOpening->title ?? '';

        $placeholders = ['{nama}', '{posisi}', '{tanggal}', '{waktu}', '{lokasi}', '{catatan}'];
        $values = [
            $applicantName, $jobTitle,
            $extra['interview_date'] ?? '-', $extra['interview_time'] ?? '-',
            $extra['location'] ?? '-', $extra['notes'] ?? '',
        ];
        $replace = fn (string $s) => str_replace($placeholders, $values, $s);

        return static::create([
            'job_application_id' => $app->id,
            'event' => $event,
            'subject' => $template ? $replace($template->subject) : static::defaultSubject($event, $jobTitle),
            'body' => $template ? $replace($template->body) : static::defaultBody($event, $applicantName, $jobTitle, $extra),
            'status' => 'draft',
        ]);
    }

    private static function defaultSubject(string $event, string $jobTitle): string
    {
        return match ($event) {
            CompanyMessageTemplate::EVENT_SCREENING_PASSED => "Selamat! Kamu Lolos Seleksi Berkas – {$jobTitle}",
            CompanyMessageTemplate::EVENT_QUIZ_SENT => "Tes Sudah Bisa Dikerjakan – {$jobTitle}",
            CompanyMessageTemplate::EVENT_QUIZ_PASSED => "Selamat! Kamu Lulus Tes – {$jobTitle}",
            CompanyMessageTemplate::EVENT_INTERVIEW_SCHEDULED => "Undangan Wawancara – {$jobTitle}",
            CompanyMessageTemplate::EVENT_MCU_SCHEDULED => "Jadwal Medical Check Up – {$jobTitle}",
            CompanyMessageTemplate::EVENT_MCU_PASSED => "Selamat! Kamu Lulus MCU – {$jobTitle}",
            CompanyMessageTemplate::EVENT_ONBOARDING_INVITE => "Selamat Bergabung! Undangan TTD & Onboarding – {$jobTitle}",
            CompanyMessageTemplate::EVENT_OFFERING_LETTER_READY => "Surat Penawaran Kerja Sudah Bisa Diunduh – {$jobTitle}",
            default => "Update Lamaran Kamu – {$jobTitle}",
        };
    }

    private static function defaultBody(string $event, string $name, string $jobTitle, array $extra = []): string
    {
        return match ($event) {
            CompanyMessageTemplate::EVENT_SCREENING_PASSED =>
                "Halo {$name},\n\nBerkas lamaran kamu untuk posisi {$jobTitle} lolos tahap seleksi berkas. Tim kami akan menghubungi kamu untuk jadwal wawancara.\n\nSalam,\nTim Rekrutmen",
            CompanyMessageTemplate::EVENT_MANUAL_REJECTED =>
                "Halo {$name},\n\nTerima kasih atas partisipasimu untuk posisi {$jobTitle}. Proses lamaranmu kami hentikan pada tahap ini."
                . (($extra['notes'] ?? '') ? "\n\nCatatan: {$extra['notes']}" : '')
                . "\n\nSalam hangat,\nTim Rekrutmen",
            default =>
                "Halo {$name},\n\nTerima kasih telah melamar untuk posisi {$jobTitle}. Pada tahap ini kami belum dapat melanjutkan proses lamaranmu.\n\nSalam hangat,\nTim Rekrutmen",
        };
    }
}
