<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCompetencies
 * @property string $id
 * @property string $name
 * @property string|null $type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\JobPositionCompetencies|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\JobPositions> $jobPositions
 * @property-read int|null $job_positions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\JobPositionCompetencies> $positionCompetencies
 * @property-read int|null $position_competencies_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competencies whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Competencies extends Model
{
    use HasUlids;

    protected $table = 'm_competencies';

    protected $fillable = ['name', 'type', 'description'];

    /**
     * Relasi many-to-many dengan job positions melalui pivot table
     */
    public function jobPositions(): BelongsToMany
    {
        return $this->belongsToMany(
            JobPositions::class,
            'm_job_position_competencies',
            'competency_id',
            'job_position_id'
        )
        ->using(JobPositionCompetencies::class)
        ->withTimestamps();
    }

    /**
     * Relasi one-to-many ke pivot table (untuk management individual records)
     */
    public function positionCompetencies(): HasMany
    {
        return $this->hasMany(JobPositionCompetencies::class, 'competency_id');
    }
}
