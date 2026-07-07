<?php

namespace App\Models\Job;

use App\Models\Candidate\CandidateJobApplications;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperJobOpenings
 */
class JobOpenings extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'job_position_id',
        'quota',
        'is_active',
        'description',
        'requirements',
    ];

    public function jobPosition()
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id', 'id');
    }

    public function applicants()
    {
        return $this->hasMany(CandidateJobApplications::class, 'job_vacancy_id', 'id');
    }
}
