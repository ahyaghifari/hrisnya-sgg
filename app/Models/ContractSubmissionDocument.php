<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 1 file lampiran pada 1 pengajuan perpanjangan kontrak. `label` = keterangan dokumen apa
 * (snapshot dari Setting `contract_extension_documents` saat unggah).
 */
class ContractSubmissionDocument extends Model
{
    use HasUlids;

    protected $fillable = [
        'contract_submission_id',
        'label',
        'path',
        'original_name',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ContractSubmission::class, 'contract_submission_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}
