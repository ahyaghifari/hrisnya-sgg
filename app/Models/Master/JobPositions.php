<?php

namespace App\Models\Master;

use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperJobPositions
 */
class JobPositions extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'm_job_positions';
    protected $fillable = [
        'company_id',
        'org_structure_id',
        'name',
        'level',
        'parent_id',
        'code',
        'description',
        'qualification',
        'job_evaluation_score',
        'workload_score',
        'planned_manpower',
        'approved_manpower',
        'actual_manpower',
        'pangkat_id',
        'golongan_id',
        'kelas_id',
        'role_id',
        'file_workload_analysis',
        'is_draft',
        'status',
        'request_type',
        'request_by',
        'approved_by',
        'approved_at',
        'rejected_at',
    ];

    public function competencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Competencies::class,
            'm_job_position_competencies',
            'job_position_id',
            'competency_id'
        )
        ->using(JobPositionCompetencies::class)
        ->withTimestamps();
    }

    public function positionCompetencies(): HasMany
    {
        return $this->hasMany(JobPositionCompetencies::class, 'job_position_id');
    }

    public function getCompetencyNamesAttribute(): string
    {
        return $this->competencies()
            ->select('name')
            ->distinct()
            ->pluck('name')
            ->unique()
            ->sort()
            ->implode(', ');
    }

    public function getCompetencyCountAttribute(): int
    {
        return $this->competencies()->distinct()->count();
    }

    public function company()
    {
        return $this->belongsTo(Companies::class);
    }

    public function orgStructure()
    {
        return $this->belongsTo(OrgStructure::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function pangkat()
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }

    public function golongan()
    {
        return $this->belongsTo(Golongans::class, 'golongan_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function defaultRole()
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }

    public function users()
    {
        return $this->hasMany(UserProfiles::class, 'job_position_id');
    }

    public function approvalSnapshots()
    {
        return $this->hasMany(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', 'jobposition_request');
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id');
    }

    public function userAssignments()
    {
        return $this->hasMany(\App\Models\User\UserJobPositions::class, 'job_position_id', 'id')
            ->whereNull('end_date')
            ->where('status', 'active');
    }
}
