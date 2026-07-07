<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperAssessmentUsers
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
