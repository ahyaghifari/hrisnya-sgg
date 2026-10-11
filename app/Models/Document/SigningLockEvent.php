<?php

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak audit blokir tanda tangan (append-only): failed / locked_temp / locked_hard / unlocked. */
class SigningLockEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const LABELS = [
        'failed' => 'Password salah',
        'locked_temp' => 'Diblokir sementara',
        'locked_hard' => 'Diblokir (perlu dibuka petugas)',
        'unlocked' => 'Blokir dibuka',
    ];

    protected $fillable = ['user_id', 'event', 'actor_id', 'document_id', 'reason', 'ip_address', 'user_agent', 'meta'];

    protected $casts = ['meta' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Jejak audit blokir tidak bisa diubah.'));
        static::deleting(fn () => throw new \LogicException('Jejak audit blokir tidak bisa dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
