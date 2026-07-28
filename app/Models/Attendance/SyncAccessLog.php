<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton (selalu 1 baris) - watermark last_synced_at buat AccessLogScheduleReader baca
 * access_logs incremental. Tidak pakai ULID/company scoping karena cuma 1 baris global.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncAccessLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncAccessLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncAccessLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncAccessLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncAccessLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncAccessLog whereLastSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncAccessLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SyncAccessLog extends Model
{
    protected $table = 'sync_access_logs';

    protected $fillable = ['last_synced_at'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public static function watermark(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
