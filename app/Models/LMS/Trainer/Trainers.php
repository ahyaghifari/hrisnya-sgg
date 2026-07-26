<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainers
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
