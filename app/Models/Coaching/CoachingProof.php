<?php

namespace App\Models\Coaching;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperCoachingProof
 */
class CoachingProof extends Model
{
    use HasUlids;

    protected $fillable = [
        'coaching_id',
        'file_path',
        'status'
    ];

    public function coaching(): BelongsTo
    {
        return $this->belongsTo(Coaching::class, 'coaching_id');
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
