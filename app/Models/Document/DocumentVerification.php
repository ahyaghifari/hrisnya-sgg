<?php

namespace App\Models\Document;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pintu masuk publik /verify/{code} (§1 docs/2026-09-28-tte-qr-internal-fase-1.md) - 1:1 dengan
 * Document. TIDAK punya kolom status - lihat App\Http\Controllers\VerificationController buat
 * cara status SAH/DATA BERUBAH/DICABUT dihitung live.
 */
class DocumentVerification extends Model
{
    use HasUlids;

    protected $fillable = [
        'document_id',
        'verification_code',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
