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
}
