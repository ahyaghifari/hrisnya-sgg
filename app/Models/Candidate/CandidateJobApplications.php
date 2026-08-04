<?php

namespace App\Models\Candidate;

use App\Models\Job\JobOpenings;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CandidateJobApplications extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'job_applications';

    protected $fillable = [
        'candidate_id',
        'job_vacancy_id',
        'current_stage',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'metadata',
        'applied_at',
        'finished_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'applied_at' => 'datetime',
        'finished_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function jobVacancy()
    {
        return $this->belongsTo(JobOpenings::class, 'job_vacancy_id');
    }

    public function recruitmentProcesses()
    {
        return $this->hasMany(CandidateRecruitmentProcess::class, 'application_id', 'id');
    }
}
