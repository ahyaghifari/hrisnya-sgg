<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskProjectMember extends Model
{
    use HasUlids;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'is_assigner',
    ];

    protected $casts = [
        'is_assigner' => 'boolean',
    ];

    /** Owner otomatis boleh assign — is_assigner cuma relevan buat member biasa. */
    public function canAssign(): bool
    {
        return $this->role === self::ROLE_OWNER || $this->is_assigner;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TaskProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
