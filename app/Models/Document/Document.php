<?php

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registry TIPIS dokumen terbit (Fase 1 TTE, docs/2026-09-28-tte-qr-internal-fase-1.md §1) -
 * TIDAK menyimpan isi surat, cuma metadata + polymorphic ke tabel sumber (`documentable`).
 * Dipakai halaman "Semua Dokumen Terbit" lintas jenis tanpa UNION manual.
 */
class Document extends Model
{
    use HasUlids;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'document_type',
        'document_number',
        'title',
        'issued_at',
        'created_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Peran yang masih menunggu tanda tangan $user, atau null kalau tidak ada (dia bukan
     * penanda tangan yang ditugaskan untuk dokumen ini, atau sudah menandatangani semua peran
     * miliknya). GENERIK lintas jenis dokumen - cuma bergantung ke Signable::assignedSigners(),
     * tidak ada kode khusus per jenis dokumen di sini. Dipakai App\Services\Document\DocumentSigningService
     * (lihat komentar di sana untuk alasan query listingnya tetap per-jenis, bukan method ini).
     */
    public function pendingRoleFor(User $user): ?string
    {
        if (! $this->relationLoaded('documentable') || ! $this->documentable instanceof \App\Contracts\Signable) {
            return null;
        }

        $signedRoles = $this->relationLoaded('signatures')
            ? $this->signatures->pluck('signer_role')
            : $this->signatures()->pluck('signer_role');

        foreach ($this->documentable->assignedSigners() as $role => $signer) {
            if ($signer?->id === $user->id && ! $signedRoles->contains($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Semua peran yang diharapkan (Signable::expectedSignerRoles()) sudah punya baris tanda
     * tangan - dipakai buat MENGUNCI isi dokumen (instruksi user 2026-09-28, menutup gap
     * "dokumen bisa diedit setelah ditandatangani" - sebelum ini hash cuma MENDETEKSI perubahan
     * setelah kejadian, sekarang dicegah dari awal). Dokumen yang documentable-nya bukan
     * Signable, atau belum punya baris documents sama sekali, dianggap BELUM fully signed
     * (aman default - jangan sampai bug lain membuat sesuatu "terkunci" secara keliru sebelum
     * memang ditandatangani).
     */
    public function isFullySigned(): bool
    {
        if (! $this->documentable instanceof \App\Contracts\Signable) {
            return false;
        }

        $signedRoles = $this->relationLoaded('signatures')
            ? $this->signatures->pluck('signer_role')
            : $this->signatures()->pluck('signer_role');

        return collect($this->documentable->expectedSignerRoles())
            ->keys()
            ->every(fn (string $role) => $signedRoles->contains($role));
    }
}
