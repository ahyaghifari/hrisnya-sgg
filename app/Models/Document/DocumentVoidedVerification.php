<?php

namespace App\Models\Document;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kode verifikasi tanda tangan yang sudah dibatalkan karena revisi (tanda tangannya sendiri sudah dihapus). */
class DocumentVoidedVerification extends Model
{
    use HasUlids;

    protected $fillable = [
        'document_id', 'verification_code', 'signer_role', 'signer_name', 'signer_position',
        'signed_at', 'reason', 'voided_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}