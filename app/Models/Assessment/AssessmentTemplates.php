<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class AssessmentTemplates extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'assessment_templates';

    protected $fillable = [
        'type_id',
        'job_position_id',
        'name',
        'description',
        'is_active',
    ];

    /**
     * assessment_templates tidak punya kolom company_id sendiri — scoping company
     * dilakukan lewat relasi ke jobPosition (yang sudah company-scoped).
     */
    protected static function booted()
    {
        if (app()->runningInConsole()) {
            return;
        }

        static::addGlobalScope('company', function (Builder $query) {
            $user = Auth::user();

            if ($user && $user->company_id && !$user->hasRole('Super Admin')) {
                $query->whereHas('jobPosition', function (Builder $q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });
            }
        });
    }

    public function assessmentType()
    {
        return $this->belongsTo(AssessmentTypes::class, 'type_id');
    }

    public function jobPosition()
    {
        return $this->belongsTo(\App\Models\Master\JobPositions::class, 'job_position_id');
    }

    public function templateItems()
    {
        return $this->hasMany(AssessmentTemplateItems::class, 'template_id');
    }
}
