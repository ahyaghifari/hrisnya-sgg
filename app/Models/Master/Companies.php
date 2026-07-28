<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCompanies
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $location
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\OrgStructure> $departments
 * @property-read int|null $departments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\JobEvaluationCompanies> $jobEvaluations
 * @property-read int|null $job_evaluations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\JobPositions> $jobPositions
 * @property-read int|null $job_positions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\OrgStructure> $orgStructure
 * @property-read int|null $org_structure_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Companies whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Companies extends Model
{
    use HasUlids;
    protected $table = "m_companies";

    protected $fillable = [
        'code',
        'name',
        'location',
        'is_active',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }

            if (empty($model->code)) {
                $lastCode = self::orderBy('code', 'desc')->first();
                $newCode = $lastCode ? (intval($lastCode->code) + 1) : 1;
                $model->code = str_pad($newCode, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function orgStructure()
    {
        return $this->hasMany(OrgStructure::class, 'company_id', 'id');
    }

    /**
     * Get departments (level 1) for this company
     */
    public function departments()
    {
        return $this->hasMany(OrgStructure::class, 'company_id', 'id')
            ->where('level', \App\Enum\OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->whereNull('parent_id')
            ->with(['head', 'children']);
    }

    public function jobPositions()
    {
        return $this->hasMany(JobPositions::class, 'company_id', 'id');
    }

    public function jobEvaluations()
    {
        return $this->hasMany(JobEvaluationCompanies::class, 'company_id', 'id');
    }

}
