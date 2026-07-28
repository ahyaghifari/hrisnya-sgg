<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateWorkExperience
 * @property string $id
 * @property string $candidate_id
 * @property string|null $company_name
 * @property string|null $business_type
 * @property string|null $company_address
 * @property string|null $position
 * @property string|null $start_date
 * @property string|null $end_date
 * @property numeric|null $salary
 * @property string|null $resign_reason
 * @property int $is_current
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereBusinessType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereCompanyAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereIsCurrent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereResignReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereSalary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateWorkExperience whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CandidateWorkExperience extends Model
{
    use HasUlids;

    protected $table = 'candt_work_experiences';

    protected $fillable = [
        'candidate_id',
        'company_name',
        'business_type',
        'company_address',
        'position',
        'start_date',
        'end_date',
        'salary',
        'resign_reason',
        'is_current',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
