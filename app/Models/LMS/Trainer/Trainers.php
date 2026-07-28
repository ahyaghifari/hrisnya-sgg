<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainers
 * @property string $id
 * @property string $type
 * @property bool $is_active
 * @property string|null $user_id
 * @property string $name
 * @property string|null $expertise
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company
 * @property string|null $address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\Trainer\TrainerAssessmentResults> $assessmentResults
 * @property-read int|null $assessment_results_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsMeetings> $meetings
 * @property-read int|null $meetings_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereExpertise($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainers whereUserId($value)
 * @mixin \Eloquent
 */
class Trainers extends Model
{
    use HasUlids;
    protected $table = 'trainers';
    protected $fillable = [
        'user_id',
        'type',
        'is_active',
        'name',
        'expertise',
        'email',
        'phone',
        'company',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function meetings()
    {
        return $this->hasMany(\App\Models\LMS\LmsMeetings::class, 'trainer_id');
    }

    public function assessmentResults()
    {
        return $this->hasMany(TrainerAssessmentResults::class, 'trainer_id');
    }
}
