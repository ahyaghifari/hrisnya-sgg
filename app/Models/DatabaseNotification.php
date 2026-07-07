<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperDatabaseNotification
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