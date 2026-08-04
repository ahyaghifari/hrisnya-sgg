<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton (selalu 1 baris) - watermark last_synced_at buat AccessLogScheduleReader baca
 * access_logs incremental. Tidak pakai ULID/company scoping karena cuma 1 baris global.
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
