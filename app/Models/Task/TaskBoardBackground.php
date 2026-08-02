<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferensi background board Tugas per user — board_key: 'todo' | 'saya' | 'individu' atau
 * ULID TaskProject. Satu baris per user+board_key (lihat unique constraint di migration).
 *
 * @property string $id
 * @property string $user_id
 * @property string $board_key
 * @property string $type
 * @property string $value
 * @property-read User $user
 */
class TaskBoardBackground extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'board_key',
        'type',
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
