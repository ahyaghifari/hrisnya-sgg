<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use App\Models\Payroll\PositionAllowance;
use App\Models\Payroll\TransportAllowance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperKelas
 */
class Kelas extends Model
{
    use HasUlids;

    protected $table = "m_kelas";

    protected $fillable = [
        'name',
        'company_id',
        'level',
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

    public function transportAllowance(): HasOne
    {
        return $this->hasOne(TransportAllowance::class, 'kelas_id');
    }

    public function positionAllowances(): HasMany
    {
        return $this->hasMany(PositionAllowance::class, 'kelas_id');
    }
}
