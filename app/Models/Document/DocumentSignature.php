<?php

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 1 tanda tangan atas 1 Document (§1 docs/2026-09-28-tte-qr-internal-fase-1.md) - `signer_name`/
 * `signer_position` snapshot saat sign, SENGAJA tidak dirujuk ke `users` (nama/jabatan boleh
 * berubah belakangan tanpa mengubah bukti tanda tangan lama). Kode verifikasi/QR (`verification`)
 * NEMPEL DI SINI, bukan di Document (instruksi user 2026-09-29) - tiap TTD punya QR sendiri,
 * personal atas nama penanda tangannya.
 */
class DocumentSignature extends Model
{
    use HasUlids;

    protected $fillable = [
        'document_id',
        'signer_role',
        'signer_id',
        'signer_name',
        'signer_position',
        'document_hash',
        'signed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    public function verification(): HasOne
    {
        return $this->hasOne(DocumentVerification::class);
    }
}
