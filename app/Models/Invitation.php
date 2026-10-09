<?php

namespace App\Models;

use App\Enum\InvitationType;
use App\Models\Sp\SpReport;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Undangan generik (waktu + tempat) ke satu karyawan, dengan atasan langsung ikut diundang.
 * `type` menentukan modul pemakainya; saat ini hanya klarifikasi SP (sebelum Berita Acara,
 * aturan di SpInvitationService).
 */
class Invitation extends Model
{
    use HasUlids;

    protected $fillable = [
        'type', 'company_id', 'user_id', 'supervisor_id', 'created_by',
        'scheduled_at', 'location', 'note',
        'cancelled_at', 'cancelled_reason',
    ];

    protected $casts = [
        'type' => InvitationType::class,
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function scopeOfType($query, InvitationType $type)
    {
        return $query->where('type', $type->value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Berita Acara yang lahir dari undangan klarifikasi SP. */
    public function report(): HasOne
    {
        return $this->hasOne(SpReport::class, 'invitation_id');
    }

    /** Coaching yang dibuat dari undangan coaching ini. */
    public function coaching(): HasOne
    {
        return $this->hasOne(\App\Models\Coaching\Coaching::class, 'invitation_id');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Masih boleh diubah/dibatalkan/ditindaklanjuti: belum dibatalkan dan belum punya BA (klarifikasi) / Coaching. */
    public function isOpen(): bool
    {
        return ! $this->isCancelled() && ! $this->report()->exists() && ! $this->coaching()->exists();
    }
}
