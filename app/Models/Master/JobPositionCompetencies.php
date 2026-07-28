<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperJobPositionCompetencies
 * @property string $id
 * @property string $job_position_id
 * @property string $competency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\Competencies $competencies
 * @property-read \App\Models\Master\JobPositions|null $jobPosition
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies whereCompetencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies whereJobPositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionCompetencies whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class JobPositionCompetencies extends Pivot
{
    use HasUlids;

    protected $table = 'm_job_position_competencies';

    protected $fillable = [
        'job_position_id',
        'competency_id',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function jobPosition()
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }

    public function competencies()
    {
        return $this->belongsTo(Competencies::class, 'competency_id');
    }
}
