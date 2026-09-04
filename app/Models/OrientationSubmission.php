<?php

namespace App\Models;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 1 pengajuan penilaian orientasi (self-service, pola Sikartap). Lampiran bukti kegiatan &
 * skor per indikator disimpan sebagai JSON di baris ini (keputusan user: 1 tabel). Indikator
 * penilaian dari Setting key `orientation_criteria` per company (bukan hardcode). Approval
 * lewat Approval Engine (module_type ORIENTATION_REQUEST); keputusan lulus/perpanjang/tidak_lulus
 * diisi penilai (atasan langsung) di step penilaian — lihat OrientationService.
 */
class OrientationSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'company_id',
        'status',
        'decision',
        'decision_note',
        'decided_by',
        'decided_at',
        'extended_until',
        'note',
        'attachments',
        'scores',
        'submitted_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'scores' => 'array',
        'decided_at' => 'datetime',
        'submitted_at' => 'datetime',
        'extended_until' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approvalSnapshot(): HasOne
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::ORIENTATION_REQUEST->value);
    }

    /** Histori approval disimpan dengan requestable_type = string enum modul (lihat
     *  ApprovalCreationService), bukan nama kelas — jadi filter manual, bukan morphMany. */
    public function approvalHistories(): HasMany
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id', 'id')
            ->where('requestable_type', ApprovalModuleEnum::ORIENTATION_REQUEST->value);
    }
}
