<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

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
