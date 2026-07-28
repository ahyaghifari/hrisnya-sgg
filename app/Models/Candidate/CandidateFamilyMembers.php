<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateFamilyMembers
 * @property string $id
 * @property string $candidate_id
 * @property string $relation
 * @property string|null $gender
 * @property string|null $name
 * @property string|null $birth_date
 * @property string|null $education
 * @property string|null $job
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereEducation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereJob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereRelation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateFamilyMembers whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CandidateFamilyMembers extends Model
{
    use HasUlids;

    protected $table = 'candt_family_members';

    protected $fillable = [
        'candidate_id',
        'relation',
        'gender',
        'name',
        'birth_date',
        'education',
        'job',
        'phone',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
