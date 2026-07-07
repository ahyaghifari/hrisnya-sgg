<?php

namespace App\Models;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as ModelsRole;

/**
 * @mixin IdeHelperRole
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