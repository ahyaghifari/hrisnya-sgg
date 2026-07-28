<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperDatabaseNotification
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property string $notifiable_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read bool $is_read
 * @property-read string $message
 * @property-read Model|\Eloquent $notifiable
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereNotifiableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereNotifiableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DatabaseNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'notifiable_id');
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    public function getMessageAttribute(): string
    {
        $data = $this->data;
        return $data['title'] ?? $data['body'] ?? 'No message';
    }
}