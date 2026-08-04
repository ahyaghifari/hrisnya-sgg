<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserTransitionAttachment extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_transition_id',
        'file_path',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(UserTransition::class, 'user_transition_id');
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
