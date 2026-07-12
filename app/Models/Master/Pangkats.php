<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use App\Models\Payroll\SalaryComponentAssignments;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperPangkats
 */
class Pangkats extends Model
{
    use HasUlids;

    protected $table = "m_pangkats";

    protected $fillable = [
        'name',
        'company_id',
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

    public function salaryComponents(): MorphMany
    {
        return $this->morphMany(SalaryComponentAssignments::class, 'salaryable');
    }

    public function selectedSalaryComponent($type)
    {
        return $this->salaryComponents()
            ->whereHas('salaryComponent', fn($q) => $q->where('name', $type))
            ->first();
    }
}
