<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission as ModelsPermission;

/**
 * @mixin IdeHelperPermission
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
