<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Riwayat email pemberitahuan pelamar (tabel `application_notifications` di DB rekrutmen,
 * dibagi — HRIS boleh BACA untuk hitung draft / tampilkan riwayat).
 *
 * HRIS TIDAK menyusun/mengirim email. Semua pemicu email = HTTP request ke project rekrutmen
 * (POST /api/integrations/hris/notifications). Rekrutmen yang menyusun email (template +
 * default) dan mengirim lewat queue-nya sendiri (queue:work).
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
            'quiz_sent' => ['url' => "{$base}/tes-online", 'label' => 'Kerjakan Tes'],
            'offering_letter_ready' => ['url' => "{$base}/dashboard", 'label' => 'Unggah Surat Penawaran'],
            'interview_scheduled', 'interview_rescheduled' => ['url' => "{$base}/lamaran/{$this->job_application_id}", 'label' => 'Lihat Jadwal Wawancara'],
            'mcu_scheduled', 'mcu_rescheduled' => ['url' => "{$base}/lamaran/{$this->job_application_id}", 'label' => 'Lihat Jadwal MCU'],
            'offering_negotiation_replied' => ['url' => "{$base}/dashboard", 'label' => 'Lihat Balasan Negosiasi'],
            default => null,
        };
    }

    // ── Pemicu email (HTTP ke rekrutmen) ──────────────────────────────────────────────────

    /** Minta rekrutmen kirim email event ini SEKARANG (event positif / sudah waktunya). */
    public static function createAndSend(JobApplication $app, string $event, array $extra = []): void
    {
        static::requestSend($app->id, ['event' => $event, 'extra' => $extra]);
    }

    /** Minta rekrutmen SIMPAN draft email event ini tanpa kirim (ditahan sampai di-flush). */
    public static function createDraftFor(JobApplication $app, string $event, array $extra = []): void
    {
        static::requestSend($app->id, ['event' => $event, 'extra' => $extra, 'hold' => true]);
    }

    /** Kirim semua draft yang ditahan untuk daftar lamaran ini. @return int jumlah diproses. */
    public static function flushDraftsFor(array $applicationIds, ?string $event = null): int
    {
        $applicationIds = array_values(array_filter($applicationIds));

        if (! $applicationIds) {
            return 0;
        }

        $res = static::rekrutmenPost('/integrations/hris/notifications/flush', array_filter([
            'job_application_ids' => $applicationIds,
            'event' => $event,
        ]));

        return (int) ($res['queued'] ?? 0);
    }

    /** Minta rekrutmen kirim email perubahan jadwal (wawancara/MCU). $kind = 'interview'|'mcu'. */
    public static function notifyReschedule(JobApplication $app, string $kind, array $data): void
    {
        $app->loadMissing('jobOpening');

        $jobTitle = $app->jobOpening->title ?? '';
        $name = $app->answersMap()['full_name'] ?? 'Pelamar';
        $label = $kind === 'mcu' ? 'Medical Check Up' : 'Wawancara';
        $event = $kind === 'mcu' ? 'mcu_rescheduled' : 'interview_rescheduled';

        $detail = 'Tanggal: ' . ($data['date'] ?? '-')
            . "\nWaktu: " . ($data['time'] ?? '-')
            . "\nLokasi: " . ($data['location'] ?? '-')
            . (($data['notes'] ?? '') !== '' ? "\nCatatan: " . $data['notes'] : '');

        static::requestSend($app->id, [
            'event' => $event,
            'subject' => "Perubahan Jadwal {$label} – {$jobTitle}",
            'body' => "Halo {$name},\n\nJadwal {$label} kamu untuk posisi {$jobTitle} telah diperbarui. Berikut jadwal terbaru:\n\n{$detail}\n\nMohon perhatikan perubahan ini. Sampai jumpa di jadwal yang baru.\n\nSalam,\nTim Rekrutmen",
        ]);
    }

    /** Minta rekrutmen kirim email balasan negosiasi offering. */
    public static function notifyNegotiationReply(JobApplication $app, string $replyMessage): void
    {
        $app->loadMissing('jobOpening');

        $jobTitle = $app->jobOpening->title ?? '';
        $name = $app->answersMap()['full_name'] ?? 'Pelamar';

        static::requestSend($app->id, [
            'event' => 'offering_negotiation_replied',
            'subject' => "Balasan Negosiasi Surat Penawaran – {$jobTitle}",
            'body' => "Halo {$name},\n\nTim rekrutmen telah membalas pengajuan negosiasimu untuk posisi {$jobTitle}:\n\n\"{$replyMessage}\"\n\nSilakan buka dashboard untuk menanggapi atau menandatangani surat penawaran.\n\nSalam,\nTim Rekrutmen",
        ]);
    }

    // ── Internal ─────────────────────────────────────────────────────────────────────────

    private static function requestSend(string $jobApplicationId, array $payload): void
    {
        static::rekrutmenPost('/integrations/hris/notifications', ['job_application_id' => $jobApplicationId] + $payload);
    }

    /** POST ke API rekrutmen. Non-fatal: gagal cuma dicatat log, aksi HRIS tetap jalan. */
    private static function rekrutmenPost(string $path, array $body): ?array
    {
        $base = rtrim((string) config('services.rekrutmen.url'), '/');
        $token = (string) config('services.rekrutmen.api_token');

        if ($base === '' || $token === '') {
            Log::warning('ApplicationNotification: config services.rekrutmen.url / api_token kosong — email tidak dikirim.', ['path' => $path]);

            return null;
        }

        try {
            $res = Http::withToken($token)->acceptJson()->timeout(8)->post("{$base}/api{$path}", $body);

            if ($res->failed()) {
                Log::warning('ApplicationNotification: request ke rekrutmen gagal.', [
                    'path' => $path, 'status' => $res->status(), 'body' => $res->body(),
                ]);

                return null;
            }

            return $res->json();
        } catch (\Throwable $e) {
            Log::warning('ApplicationNotification: request ke rekrutmen error.', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
