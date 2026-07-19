<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use App\Models\Payroll\PangkatBpjsBasis;
use App\Models\Payroll\PangkatLemburRate;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\PositionAllowance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User\UserProfiles;
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
        'start_kelas_id',
        'max_kelas_id',
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

    public function startKelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'start_kelas_id');
    }

    public function maxKelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'max_kelas_id');
    }

    public function pangkatSalary(): HasOne
    {
        return $this->hasOne(PangkatSalary::class, 'pangkat_id');
    }

    public function positionAllowances(): HasMany
    {
        return $this->hasMany(PositionAllowance::class, 'pangkat_id');
    }

    public function bpjsBasis(): HasOne
    {
        return $this->hasOne(PangkatBpjsBasis::class, 'pangkat_id');
    }

    public function lemburRate(): HasOne
    {
        return $this->hasOne(PangkatLemburRate::class, 'pangkat_id');
    }

    public function jobPositions(): HasMany
    {
        return $this->hasMany(JobPositions::class, 'pangkat_id');
    }

    public function userProfiles(): HasMany
    {
        return $this->hasMany(UserProfiles::class, 'pangkat_id');
    }
}
