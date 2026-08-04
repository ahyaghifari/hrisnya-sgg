<?php

namespace App\Models\Master;

use App\Models\LMS\LmsCourseCompetencies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function lmsCourseCompetencies(): HasMany
    {
        return $this->hasMany(LmsCourseCompetencies::class, 'competency_id');
    }
}
