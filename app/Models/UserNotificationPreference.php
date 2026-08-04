<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferensi on/off notifikasi per user (App\Enum\NotificationTypeEnum). Tidak ada baris untuk
 * kombinasi user+type tertentu = default enabled (opt-out, bukan opt-in) - lihat
 * NotificationPreferenceService::isEnabled().
 */
class UserNotificationPreference extends Model
{
    use HasUlids;

    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'user_id',
        'notification_type',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
