<?php

namespace App\Models;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as ModelsRole;

/**
 * @mixin IdeHelperRole
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static Builder<static>|Role forCompany($companyId)
 * @method static Builder<static>|Role global()
 * @method static Builder<static>|Role newModelQuery()
 * @method static Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static Builder<static>|Role query()
 * @method static Builder<static>|Role whereCompanyId($value)
 * @method static Builder<static>|Role whereCreatedAt($value)
 * @method static Builder<static>|Role whereGuardName($value)
 * @method static Builder<static>|Role whereId($value)
 * @method static Builder<static>|Role whereName($value)
 * @method static Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 * @mixin \Eloquent
 */
class Role extends ModelsRole
{
    protected $fillable = ['name', 'guard_name', 'company_id'];

    // protected static function booted()
    // {
    //     if (app()->runningInConsole()) {
    //         return;
    //     }

    //     static::addGlobalScope('company', function (Builder $query) {
    //         $user = Auth::user();
    //         if ($user && $user->company_id) {
    //             $query->where('company_id', $user->company_id);
    //         }
    //     });
    // }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('company_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}