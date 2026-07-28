<?php

namespace App\Models\Candidate;

use App\Models\Job\JobOpenings;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperCandidateJobApplications
 * @property string $id
 * @property string $candidate_id
 * @property string $job_vacancy_id
 * @property string $current_stage
 * @property string $status
 * @property string|null $approval_status
 * @property string|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon $applied_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed>|null $metadata
 * @property-read \App\Models\User|null $approver
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @property-read JobOpenings|null $jobVacancy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateRecruitmentProcess> $recruitmentProcesses
 * @property-read int|null $recruitment_processes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereAppliedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereApprovalStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereCurrentStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereJobVacancyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateJobApplications withoutTrashed()
 * @mixin \Eloquent
 */
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
