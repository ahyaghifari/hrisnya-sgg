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
        'fixed_at',
        'finalized_at',
        'final_hash',
        'file_path',
        'file_hash',
        'created_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'fixed_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(DocumentEvent::class);
    }

    /** Catat event audit; ip/user agent null-safe (CLI/queue tidak punya request). */
    public function logEvent(string $event, ?User $actor = null, array $meta = []): void
    {
        $this->events()->create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'meta' => $meta ?: null,
        ]);
    }

    /** FIX/LOCK: isi dikunci, tanda tangan boleh jalan. Idempotent. */
    public function fix(?User $actor = null): void
    {
        if ($this->fixed_at) {
            return;
        }

        $this->update(['fixed_at' => now()]);
        $this->logEvent('document_fixed', $actor);
    }

    /** Semua TTD selesai tapi PDF final belum jadi (job render masih jalan). */
    public function isFinalizing(): bool
    {
        return ! $this->finalized_at && $this->isFullySigned();
    }

    /** Kembali ke draft (mis. karyawan minta klarifikasi ulang) - hanya sebelum ada tanda tangan. */
    public function unfix(?User $actor = null): void
    {
        abort_if($this->signatures()->exists(), 409, 'Dokumen sudah ditandatangani, tidak bisa dibuka kuncinya.');

        if ($this->fixed_at) {
            $this->update(['fixed_at' => null]);
            $this->logEvent('document_unfixed', $actor);
        }
    }

    /**
     * Revisi setelah FIX: semua tanda tangan yang sudah masuk dibatalkan (isi akan berubah, hash lama tidak
     * sah lagi) dan kunci dibuka. Jejaknya (siapa, hash, kode verifikasi) disimpan di event audit.
     * Hanya sebelum dokumen selesai ditandatangani semua pihak.
     */
    public function revise(?User $actor, string $reason): void
    {
        abort_if($this->finalized_at || $this->isFullySigned(), 409, 'Dokumen sudah selesai ditandatangani, tidak bisa direvisi.');

        $signatures = $this->signatures()->with('verification')->get();
        $voided = $signatures->map(fn ($s) => [
            'role' => $s->signer_role,
            'signer' => $s->signer_name,
            'signed_at' => $s->signed_at?->toDateTimeString(),
            'hash' => $s->document_hash,
            'code' => $s->verification?->verification_code,
        ])->all();

        \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $reason, $voided, $signatures) {
            foreach ($signatures as $s) {
                if ($s->verification) {
                    DocumentVoidedVerification::create([
                        'document_id' => $this->id,
                        'verification_code' => $s->verification->verification_code,
                        'signer_role' => $s->signer_role,
                        'signer_name' => $s->signer_name,
                        'signer_position' => $s->signer_position,
                        'signed_at' => $s->signed_at,
                        'reason' => $reason,
                        'voided_at' => now(),
                    ]);
                }
            }

            $signatures->each->delete();
            $this->unsetRelation('signatures');
            $this->unfix($actor);
            $this->logEvent('document_revised', $actor, ['reason' => $reason, 'voided_signatures' => $voided]);
        });
    }

    /** Semua TTD wajib selesai: catat waktu, hash payload akhir, dan PDF final yang sudah disimpan
     *  (lihat DocumentFinalizer). Idempotent. */
    public function finalize(string $filePath, string $fileHash): void
    {
        if ($this->finalized_at) {
            return;
        }

        $this->update([
            'finalized_at' => now(),
            'final_hash' => hash('sha256', json_encode($this->documentable->signablePayload())),
            'file_path' => $filePath,
            'file_hash' => $fileHash,
        ]);
        $this->logEvent('document_finalized', null, ['file_hash' => $fileHash]);
    }

    protected static function booted(): void
    {
        // Dokumen final immutable: PDF & hash-nya tidak boleh diganti lewat jalur apa pun.
        static::updating(function (Document $document) {
            if ($document->getOriginal('finalized_at') && $document->isDirty(['finalized_at', 'final_hash', 'file_path', 'file_hash'])) {
                throw new \LogicException('Dokumen sudah final, PDF dan hash-nya tidak bisa diubah.');
            }
        });
    }

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
