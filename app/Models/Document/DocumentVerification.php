<?php

namespace App\Models\Document;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pintu masuk publik /verify/{code} (§1 docs/2026-09-28-tte-qr-internal-fase-1.md, direvisi
 * instruksi user 2026-09-29) - 1:1 dengan DocumentSignature, BUKAN Document lagi. Tiap TTD
 * (created_by, knows_by, dst) punya kode/QR sendiri-sendiri, personal atas nama penanda
 * tangannya - bukan 1 kode yang ditempel bareng ke semua peran. TIDAK punya kolom status -
 * lihat App\Http\Controllers\Public\VerificationController buat cara status SAH/DATA
 * BERUBAH/DICABUT dihitung live.
 */
class DocumentVerification extends Model
{
    use HasUlids;

    protected $fillable = [
        'document_signature_id',
        'verification_code',
    ];

    public function signature(): BelongsTo
    {
        return $this->belongsTo(DocumentSignature::class, 'document_signature_id');
    }
}
