<?php

namespace App\Models\Master;

use App\Enum\EmployeeTypeCategory;
use App\Helpers\PermissionHelper;
use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperEmployeeTypes
 * @property string $id
 * @property string $name
 * @property string|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property EmployeeTypeCategory $category
 * @property-read \App\Models\Master\Companies|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserProfiles> $userProfiles
 * @property-read int|null $user_profiles_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeTypes whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class EmployeeTypes extends Model
{
    use HasUlids;

    protected $table = 'm_employee_types';

    protected $fillable = [
        'name',
        'category',
        'company_id',
    ];

    protected $casts = [
        'category' => EmployeeTypeCategory::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }

            if (Auth::check() && !PermissionHelper::hasGlobalRole(Auth::user())) {
                $model->company_id = Auth::user()->company_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && !PermissionHelper::hasGlobalRole(Auth::user())) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function userProfiles()
    {
        return $this->hasMany(UserProfiles::class, 'employee_type_id');
    }

}
