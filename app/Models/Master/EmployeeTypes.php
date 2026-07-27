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
