<?php

namespace App\Models\Master;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Concerns\LogsAuditChanges;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperJobPositions
 * @property string $id
 * @property string $company_id
 * @property string|null $org_structure_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property string|null $qualification
 * @property int|null $job_evaluation_score
 * @property int|null $workload_score
 * @property string|null $file_workload_analysis
 * @property int|null $planned_manpower
 * @property int $approved_manpower
 * @property int|null $actual_manpower
 * @property int|null $level
 * @property string|null $parent_id
 * @property int $is_draft
 * @property string $status
 * @property string|null $request_type
 * @property string|null $request_by
 * @property string|null $approved_by
 * @property string|null $approved_at
 * @property string|null $rejected_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $pangkat_id
 * @property string|null $kelas_id
 * @property int|null $role_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalHistories> $approvalHistories
 * @property-read int|null $approval_histories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalSnapshots> $approvalSnapshots
 * @property-read int|null $approval_snapshots_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JobPositions> $children
 * @property-read int|null $children_count
 * @property-read \App\Models\Master\Companies $company
 * @property-read \App\Models\Master\JobPositionCompetencies|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\Competencies> $competencies
 * @property-read int|null $competencies_count
 * @property-read \App\Models\Role|null $defaultRole
 * @property-read int $competency_count
 * @property-read string $competency_names
 * @property-read \App\Models\Master\Kelas|null $kelas
 * @property-read \App\Models\Master\OrgStructure|null $orgStructure
 * @property-read \App\Models\Master\Pangkats|null $pangkat
 * @property-read JobPositions|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\JobPositionCompetencies> $positionCompetencies
 * @property-read int|null $position_competencies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User\UserJobPositions> $userAssignments
 * @property-read int|null $user_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserProfiles> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereActualManpower($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereApprovedManpower($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereFileWorkloadAnalysis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereIsDraft($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereJobEvaluationScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereKelasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereOrgStructureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions wherePangkatId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions wherePlannedManpower($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereQualification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereRejectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereRequestBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereRequestType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions whereWorkloadScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositions withoutTrashed()
 * @mixin \Eloquent
 */
class JobPositions extends Model
{
    use HasUlids, SoftDeletes;
    use LogsAuditChanges;

    protected $table = 'm_job_positions';
    protected $fillable = [
        'company_id',
        'org_structure_id',
        'is_internship',
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
            ->where('module_type', ApprovalModuleEnum::JOBPOSITION_REQUEST->value);
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

    // ── Riwayat perubahan JE (job_evaluation_score) — dipakai tab Riwayat halaman Job
    // Evaluation (Kepegawaian). JE jarang berubah, tapi tetap butuh jejak siapa & kapan. ──

    protected function auditModule(): string
    {
        return 'kepegawaian';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->company_id;
    }

    protected function auditKey(): string
    {
        return 'job_evaluation_score:' . $this->id;
    }

    protected function auditLabel(): ?string
    {
        return 'JE Jabatan — ' . $this->name;
    }

    protected function auditWatchedAttribute(): string
    {
        return 'job_evaluation_score';
    }
}
