<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Varian BelongsToCompany untuk master data yang company_id-nya NULLABLE:
 * baris dengan company_id NULL dianggap "berlaku untuk semua company" (shared),
 * baris dengan company_id terisi cuma kelihatan oleh company itu (+ Super Admin).
 */
trait BelongsToCompanyOrShared
{
    protected static function bootBelongsToCompanyOrShared()
    {
        if (app()->runningInConsole()) {
            return;
        }

        static::addGlobalScope('company', function (Builder $query) {
            $user = Auth::user();
            $table = $query->getModel()->getTable();

            if ($user && $user->company_id && !$user->hasRole('Super Admin')) {
                $query->where(function (Builder $q) use ($table, $user) {
                    $q->where($table . '.company_id', $user->company_id)
                        ->orWhereNull($table . '.company_id');
                });
            }
        });
    }
}
