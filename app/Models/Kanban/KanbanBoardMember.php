<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanBoardMember extends Model
{
    use HasUlids;

    public const ROLE_OWNER = 'owner';
    public const ROLE_ASSIGNER = 'assigner';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'board_id',
        'user_id',
        'role',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
