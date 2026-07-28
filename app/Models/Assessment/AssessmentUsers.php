<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperAssessmentUsers
 * @property string $id
 * @property string $assessment_id
 * @property string $target_id
 * @property string $created_by
 * @property string $status
 * @property string|null $submitted_at
 * @property numeric|null $final_score
 * @property string|null $final_category
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Assessment\Assessments|null $assessments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Assessment\AssessmentScores> $scores
 * @property-read int|null $scores_count
 * @property-read \App\Models\User|null $target
 * @method static Builder<static>|AssessmentUsers newModelQuery()
 * @method static Builder<static>|AssessmentUsers newQuery()
 * @method static Builder<static>|AssessmentUsers onlyTrashed()
 * @method static Builder<static>|AssessmentUsers query()
 * @method static Builder<static>|AssessmentUsers whereAssessmentId($value)
 * @method static Builder<static>|AssessmentUsers whereCreatedAt($value)
 * @method static Builder<static>|AssessmentUsers whereCreatedBy($value)
 * @method static Builder<static>|AssessmentUsers whereDeletedAt($value)
 * @method static Builder<static>|AssessmentUsers whereFinalCategory($value)
 * @method static Builder<static>|AssessmentUsers whereFinalScore($value)
 * @method static Builder<static>|AssessmentUsers whereId($value)
 * @method static Builder<static>|AssessmentUsers whereStatus($value)
 * @method static Builder<static>|AssessmentUsers whereSubmittedAt($value)
 * @method static Builder<static>|AssessmentUsers whereTargetId($value)
 * @method static Builder<static>|AssessmentUsers whereUpdatedAt($value)
 * @method static Builder<static>|AssessmentUsers withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AssessmentUsers withoutTrashed()
 * @mixin \Eloquent
 */
class AssessmentUsers extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'assessment_users';

    /**
     * assessment_users tidak punya kolom company_id sendiri — scoping company
     * dilakukan lewat relasi ke assessments (yang sudah company-scoped).
     */
    protected static function booted()
    {
        if (app()->runningInConsole()) {
            return;
        }

        static::addGlobalScope('company', function (Builder $query) {
            $user = Auth::user();

            if ($user && $user->company_id && !$user->hasRole('Super Admin')) {
                $query->whereHas('assessments', function (Builder $q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });
            }
        });
    }

    protected $fillable = [
        'assessment_id',
        'target_id',
        'created_by',
        'status',
        'submitted_at',
        'final_score',
        'final_category',
    ];

    public function assessments()
    {
        return $this->hasOne(Assessments::class, 'id', 'assessment_id');
    }

    public function target()
    {
        return $this->belongsTo(\App\Models\User::class, 'target_id');
    }

    public function scores()
    {
        return $this->hasMany(AssessmentScores::class, 'assessment_user_id');
    }
}
