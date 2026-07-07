<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany()
    {
        // skip scoping saat running di console (migrate/seed/artisan/queue)
        if (app()->runningInConsole()) {
            return;
        }

        static::addGlobalScope('company', function (Builder $query) {
            $user = Auth::user();

            if ($user && $user->company_id && !$user->hasRole('Super Admin')) {
                $query->where($query->getModel()->getTable() . '.company_id', $user->company_id);
            }
        });
    }
}
