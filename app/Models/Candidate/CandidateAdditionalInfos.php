<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateAdditionalInfos
 * @property string $id
 * @property string $candidate_id
 * @property int|null $allow_reference_check
 * @property int|null $has_disease
 * @property string|null $disease_notes
 * @property string|null $hobbies
 * @property string|null $languages
 * @property string|null $last_facilities
 * @property string|null $expected_salary_facilities
 * @property string|null $available_start_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereAllowReferenceCheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereAvailableStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereDiseaseNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereExpectedSalaryFacilities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereHasDisease($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereHobbies($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereLastFacilities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateAdditionalInfos whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CandidateAdditionalInfos extends Model
{
    use HasUlids;

    protected $table = 'candt_additional_infos';

    protected $fillable = [
        'candidate_id',
        'allow_reference_check',
        'has_disease',
        'disease_notes',
        'hobbies',
        'languages',
        'last_facilities',
        'expected_salary_facilities',
        'available_start_date',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
