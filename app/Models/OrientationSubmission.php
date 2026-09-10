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
 * (`assessments` -> `scores`) + memilih keputusan (lanjut_kontrak / tidak_dilanjutkan). Indikator =
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
        'effective_end_date',
        'note',
        'attachments',
        'submitted_at',
        'presentation_proposed_at',
        'presentation_confirmed_at',
        'presentation_note',
        'presentation_decided_by',
        'presentation_decided_at',
        'presentation_hr_at',
        'presentation_hr_by',
        'presentation_support_at',
        'presentation_support_by',
    ];

    protected $casts = [
        'attachments' => 'array',
        'decided_at' => 'datetime',
        'submitted_at' => 'datetime',
        'extended_until' => 'date',
        'effective_end_date' => 'date',
        'presentation_proposed_at' => 'datetime',
        'presentation_confirmed_at' => 'datetime',
        'presentation_decided_at' => 'datetime',
        'presentation_hr_at' => 'datetime',
        'presentation_support_at' => 'datetime',
    ];

    /** Jadwal presentasi sudah disetujui HR & jabatan pendukung (slot terkunci). */
    public function presentationFullyApproved(): bool
    {
        return $this->presentation_confirmed_at
            && $this->presentation_hr_at
            && $this->presentation_support_at;
    }

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

    public function presentationHrBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presentation_hr_by');
    }

    public function presentationSupportBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presentation_support_by');
    }

    public function presentationDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presentation_decided_by');
    }

    /** Indikator penilaian (snapshot) + skor per bulan-nya. */
    public function assessments(): HasMany
    {
        return $this->hasMany(OrientationSubmissionAssessment::class, 'orientation_submission_id')
            ->orderBy('sort_order');
    }
}
