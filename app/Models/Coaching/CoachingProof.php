<?php

namespace App\Models\Coaching;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperCoachingProof
 * @property string $id
 * @property string $coaching_id
 * @property string $file_path
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Coaching\Coaching $coaching
 * @property-read string $file_url
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof whereCoachingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoachingProof whereUpdatedAt($value)
 * @mixin \Eloquent
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
