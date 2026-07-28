<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateEducations
 * @property string $id
 * @property string $candidate_id
 * @property string $level
 * @property string|null $school_name
 * @property string|null $major
 * @property int|null $gpa
 * @property string|null $start_year
 * @property string|null $end_year
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereEndYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereGpa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereMajor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereSchoolName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereStartYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateEducations whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CandidateEducations extends Model
{
    use HasUlids;

    protected $table = 'candt_educations';

    protected $fillable = [
        'candidate_id',
        'level',
        'school_name',
        'major',
        'gpa',
        'start_year',
        'end_year',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
