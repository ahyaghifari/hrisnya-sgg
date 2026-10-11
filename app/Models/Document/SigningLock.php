<?php

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status blokir tanda tangan per akun (1 baris per user). Logika ada di SigningLockService. */
class SigningLock extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'failed_attempts', 'last_failed_at', 'locked_until',
        'temp_lock_count', 'temp_lock_window_started_at', 'hard_locked_at',
    ];

    protected $casts = [
        'last_failed_at' => 'datetime',
        'locked_until' => 'datetime',
        'temp_lock_window_started_at' => 'datetime',
        'hard_locked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHardLocked(): bool
    {
        return $this->hard_locked_at !== null;
    }

    public function isTempLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
