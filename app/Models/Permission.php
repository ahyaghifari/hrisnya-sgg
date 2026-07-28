<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission as ModelsPermission;

/**
 * @mixin IdeHelperPermission
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ModelsPermission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static Builder<static>|Permission newModelQuery()
 * @method static Builder<static>|Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission permission($permissions, $without = false)
 * @method static Builder<static>|Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission role($roles, $guard = null, $without = false)
 * @method static Builder<static>|Permission whereCompanyId($value)
 * @method static Builder<static>|Permission whereCreatedAt($value)
 * @method static Builder<static>|Permission whereGuardName($value)
 * @method static Builder<static>|Permission whereId($value)
 * @method static Builder<static>|Permission whereName($value)
 * @method static Builder<static>|Permission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
class Permission extends ModelsPermission
{
    protected $fillable = ['name', 'guard_name', 'company_id'];

    protected static function booted()
    {
        // skip scoping saat running di console (migrate/seed/artisan)
        if (app()->runningInConsole()) {
            return;
        }

        static::addGlobalScope('company', function (Builder $query) {
            $user = Auth::user();
            // kalau ada user dan user punya company_id -> filter
            if ($user && $user->company_id) {
                $query->where('company_id', $user->company_id);
            }
        });
    }
}
