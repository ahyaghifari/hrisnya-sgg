<?php

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit dokumen (append-only): document_created/fixed/unfixed/signature_viewed/
 * signature_failed/signed/finalized/revised/revoked. Internal, tidak tampil di verifikasi publik.
 * Ditampilkan oleh komponen App\Livewire\Document\AuditTrail.
 */
class DocumentEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** Label tampil per jenis event; event tak dikenal tampil apa adanya. */
    public const LABELS = [
        'document_created' => 'Dokumen diterbitkan',
        'document_fixed' => 'Isi dokumen dikunci',
        'document_unfixed' => 'Kunci isi dokumen dibuka',
        'signature_viewed' => 'Halaman tanda tangan dibuka',
        'signature_failed' => 'Percobaan tanda tangan gagal',
        'document_signed' => 'Ditandatangani',
        'document_finalized' => 'PDF final dibuat',
        'document_revised' => 'Direvisi, tanda tangan dibatalkan',
        'document_revoked' => 'Dokumen dibatalkan',
    ];

    protected $fillable = ['document_id', 'event', 'actor_id', 'ip_address', 'user_agent', 'meta'];

    protected $casts = ['meta' => 'array'];

    protected static function booted(): void
    {
        // Append-only: jejak audit tidak boleh diubah atau dihapus lewat model.
        static::updating(fn () => throw new \LogicException('Jejak audit dokumen tidak bisa diubah.'));
        static::deleting(fn () => throw new \LogicException('Jejak audit dokumen tidak bisa dihapus.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? $this->event;
    }
}
