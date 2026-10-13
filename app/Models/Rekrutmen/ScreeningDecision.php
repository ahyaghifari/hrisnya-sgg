<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Draft keputusan seleksi berkas (DB rekrutmen_db). Port dari project `rekrutmen`.
 * Pola: `saveDraftForOpening` tulis draft (belum ubah status) -> `applyForOpening`
 * terapkan (ubah status + buat draft notifikasi). Pengiriman email di Fase 8.
 */
class ScreeningDecision extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['job_application_id', 'decision'];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    /**
     * Simpan draft keputusan 1 lowongan — hapus draft lama, tulis baru. Satu transaksi + kunci baris
     * lamaran lowongan itu, jadi gagal di tengah tidak meninggalkan draft setengah jadi dan dua
     * recruiter yang simpan bersamaan diserialkan (yang terakhir menang, tapi utuh).
     */
    public static function saveDraftForOpening(string $jobOpeningId, array $passedIds, array $allScreeningIds): void
    {
        (new static)->getConnection()->transaction(function () use ($jobOpeningId, $passedIds, $allScreeningIds) {
            // Hanya id yang memang berstatus screening di lowongan ini (abaikan id sisipan dari client).
            $validIds = JobApplication::where('job_opening_id', $jobOpeningId)
                ->where('status', 'screening')
                ->whereIn('id', $allScreeningIds)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            static::whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $jobOpeningId))->delete();

            foreach ($validIds as $appId) {
                static::create([
                    'job_application_id' => $appId,
                    'decision' => in_array($appId, $passedIds) ? 'passed' : 'rejected',
                ]);
            }
        });
    }

    /**
     * Terapkan semua draft 1 lowongan: yang 'passed' -> status `shortlisted` + email lolos
     * LANGSUNG dikirim (createAndSend); yang 'rejected' -> status TETAP `screening`, notif
     * penolakan cuma draft (ditahan, dikirim manual lewat "Kirim Email Pemberitahuan").
     *
     * @return array{0:int,1:int} [jumlah lolos, jumlah tidak lolos]
     */
    public static function applyForOpening(string $jobOpeningId): array
    {
        $decisions = static::with(['jobApplication.answers', 'jobApplication.jobOpening'])
            ->whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $jobOpeningId))
            ->get();

        $log = \Illuminate\Support\Facades\Log::channel('rekrutmen');
        $log->info('applyForOpening dipanggil.', [
            'job_opening_id' => $jobOpeningId,
            'jumlah_draft_keputusan' => $decisions->count(),
            'passed_draft' => $decisions->where('decision', 'passed')->count(),
        ]);

        if ($decisions->isEmpty()) {
            $log->warning('applyForOpening: TIDAK ADA draft keputusan untuk lowongan ini - tidak ada email dikirim. Jalankan "Seleksi Berkas" (Simpan Draft) dulu.', ['job_opening_id' => $jobOpeningId]);
        }

        $passed = 0;
        $rejected = 0;

        foreach ($decisions as $d) {
            $app = $d->jobApplication;
            if ($d->decision === 'passed') {
                $app->update(['status' => JobApplication::STATUS_SHORTLISTED]);
                ApplicationNotification::createAndSend($app, CompanyMessageTemplate::EVENT_SCREENING_PASSED);
                $passed++;
            } else {
                ApplicationNotification::createDraftFor($app, CompanyMessageTemplate::EVENT_SCREENING_REJECTED);
                $rejected++;
            }
            $d->delete();
        }

        if ($passed > 0) {
            JobOpeningStageLog::record($jobOpeningId, 'screening', 'shortlisted', $passed);
        }

        $log->info('applyForOpening selesai.', ['job_opening_id' => $jobOpeningId, 'lolos' => $passed, 'tidak_lolos' => $rejected]);

        return [$passed, $rejected];
    }
}
