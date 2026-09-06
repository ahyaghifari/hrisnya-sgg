<?php

namespace App\Models;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 pengajuan penilaian orientasi (self-service). Karyawan orientasi mengajukan + upload bukti
 * kegiatan, lalu penilai (resolve dari `sikartap_reviewers` context `orientation`, lihat
 * App\Services\SikartapReviewerResolver) mengisi skor per indikator per bulan
 * (`assessments` -> `scores`) + memilih keputusan (lulus/perpanjang/tidak_lulus). Indikator =
 * snapshot dari Setting key `orientation_criteria` per company. **Tidak lewat Approval Engine**
 * (dilepas 2026-09-06) — 1 penilai memutuskan, bukan routing berjenjang.
 *
 * Status: draft -> diajukan -> dinilai. (dibatalkan = dibatalkan pengaju sebelum dinilai.)
 */
class OrientationSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'user_orientation_id',
        'company_id',
        'status',
        'decision',
        'decision_note',
        'decided_by',
        'decided_at',
        'extended_until',
        'note',
        'attachments',
        'submitted_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'decided_at' => 'datetime',
        'submitted_at' => 'datetime',
        'extended_until' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Periode orientasi (user_orientations) yang dinilai lewat pengajuan ini. */
    public function userOrientation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User\UserOrientation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Indikator penilaian (snapshot) + skor per bulan-nya. */
    public function assessments(): HasMany
    {
        return $this->hasMany(OrientationSubmissionAssessment::class, 'orientation_submission_id')
            ->orderBy('sort_order');
    }
}
