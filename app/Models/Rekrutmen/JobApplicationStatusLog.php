<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan status lamaran (DB `rekrutmen_db`). `changed_by` = FK user rekrutmen
 * (diisi kalau aksi jalan di project `rekrutmen`); `changed_by_name` = snapshot nama aktor
 * HRIS (kolom ditambah migration di repo `rekrutmen`, 2026-09-04). Lihat plan porting.
 */
class JobApplicationStatusLog extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = [
        'job_application_id', 'from_status', 'to_status', 'changed_by', 'changed_by_name', 'changed_at',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** Nama aktor untuk ditampilkan: snapshot HRIS kalau ada, kalau tidak user rekrutmen. */
    public function actorName(): string
    {
        return $this->changed_by_name ?: ($this->changedBy?->name ?? 'Sistem');
    }
}
