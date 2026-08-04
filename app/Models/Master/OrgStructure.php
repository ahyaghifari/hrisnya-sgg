<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrgStructure extends Model
{
    use HasUlids;

    protected $table = 'm_org_structures';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'capacity',
        'head_id',
        'parent_id',
        'level',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $user = Auth::user();

            if ($user && !PermissionHelper::hasGlobalRole($user)) {
                $model->company_id = $user->company_id;
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(OrgStructure::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OrgStructure::class, 'parent_id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    /**
     * Get divisions for this department (level 2)
     */
    public function divisions()
    {
        return $this->hasMany(OrgStructure::class, 'parent_id', 'id')
            ->where('level', \App\Enum\OrgStructurLevel::LEVEL_DIVISION->value)
            ->with(['head', 'children']);
    }

    /**
     * Get subdivisions for this division (level 3)
     */
    public function subdivisions()
    {
        return $this->hasMany(OrgStructure::class, 'parent_id', 'id')
            ->where('level', \App\Enum\OrgStructurLevel::LEVEL_SUBDIVISION->value)
            ->with(['head']);
    }

    public function jobPositions()
    {
        return $this->hasMany(JobPositions::class, 'org_structure_id', 'id');
    }
}
