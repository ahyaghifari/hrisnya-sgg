<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperOrgStructure
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $code
 * @property int|null $capacity
 * @property string|null $head_id
 * @property string|null $parent_id
 * @property int $level
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrgStructure> $children
 * @property-read int|null $children_count
 * @property-read \App\Models\Master\Companies $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrgStructure> $divisions
 * @property-read int|null $divisions_count
 * @property-read User|null $head
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\JobPositions> $jobPositions
 * @property-read int|null $job_positions_count
 * @property-read OrgStructure|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrgStructure> $subdivisions
 * @property-read int|null $subdivisions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereHeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStructure whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
