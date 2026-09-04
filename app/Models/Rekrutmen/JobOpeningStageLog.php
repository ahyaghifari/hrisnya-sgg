<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap "N kandidat pindah ke tahap X, oleh siapa, kapan" per lowongan. Read-only dari HRIS
 * di Fase 1 (ditampilkan di aksi Riwayat JobOpeningResource). Penulisan (::record) menyusul
 * di fase yang menggerakkan kandidat.
 */
class JobOpeningStageLog extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    public $timestamps = false;

    protected $fillable = ['job_opening_id', 'from_status', 'to_status', 'count', 'done_by', 'done_at'];

    protected $casts = ['done_at' => 'datetime'];

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /**
     * Catat rollup "N kandidat: from -> to". Ditulis dari HRIS: `done_by` null (id user
     * HRIS ≠ id user rekrutmen_db). Nama aktor per-kandidat sudah tercatat di
     * job_application_status_logs.changed_by_name.
     */
    public static function record(string $jobOpeningId, ?string $from, string $to, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        static::create([
            'job_opening_id' => $jobOpeningId,
            'from_status' => $from,
            'to_status' => $to,
            'count' => $count,
            'done_by' => null,
            'done_at' => now(),
        ]);
    }
}
