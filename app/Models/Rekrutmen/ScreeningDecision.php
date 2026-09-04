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

    /** Simpan draft keputusan 1 lowongan — hapus draft lama, tulis baru. */
    public static function saveDraftForOpening(string $jobOpeningId, array $passedIds, array $allScreeningIds): void
    {
        static::whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $jobOpeningId))->delete();

        foreach ($allScreeningIds as $appId) {
            static::create([
                'job_application_id' => $appId,
                'decision' => in_array($appId, $passedIds) ? 'passed' : 'rejected',
            ]);
        }
    }

    /**
     * Terapkan semua draft 1 lowongan: yang 'passed' -> status `shortlisted` + draft notif
     * lolos; yang 'rejected' -> status TETAP `screening` (belum ditolak), cuma draft notif
     * penolakan dibuat untuk dikirim manual nanti.
     *
     * @return array{0:int,1:int} [jumlah lolos, jumlah tidak lolos]
     */
    public static function applyForOpening(string $jobOpeningId): array
    {
        $decisions = static::with(['jobApplication.answers', 'jobApplication.jobOpening'])
            ->whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $jobOpeningId))
            ->get();

        $passed = 0;
        $rejected = 0;

        foreach ($decisions as $d) {
            $app = $d->jobApplication;
            if ($d->decision === 'passed') {
                $app->update(['status' => JobApplication::STATUS_SHORTLISTED]);
                ApplicationNotification::createDraftFor($app, CompanyMessageTemplate::EVENT_SCREENING_PASSED);
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

        return [$passed, $rejected];
    }
}
