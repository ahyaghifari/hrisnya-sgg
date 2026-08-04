<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
