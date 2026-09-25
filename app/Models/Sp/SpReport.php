<?php

namespace App\Models\Sp;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berita Acara - 1:1 dengan employee_sps (instruksi user 2026-09-25). Catatan investigasi HC-GA
 * sebelum SP terbit, HR-internal (TIDAK ditampilkan ke karyawan lewat portal self-service - lihat
 * SuratPeringatanDetailPage).
 */
class SpReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'sp_id',
        'nomor',
        'kejadian',
        'dasar_klarifikasi',
        'subject_clarification',
        'analysis',
        'kesimpulan',
        'followup_plan',
    ];

    public function sp(): BelongsTo
    {
        return $this->belongsTo(EmployeeSp::class, 'sp_id');
    }
}
