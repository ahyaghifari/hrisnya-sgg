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
 * @property string $id
 * @property string $name
 * @property string|null $golongan
 * @property int|null $level
 * @property string|null $company_id
 * @property string|null $start_kelas_id
 * @property string|null $max_kelas_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PangkatBpjsBasis|null $bpjsBasis
 * @property-read \App\Models\Master\Companies|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Master\JobPositions> $jobPositions
 * @property-read int|null $job_positions_count
 * @property-read PangkatLemburRate|null $lemburRate
 * @property-read \App\Models\Master\Kelas|null $maxKelas
 * @property-read PangkatSalary|null $pangkatSalary
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PositionAllowance> $positionAllowances
 * @property-read int|null $position_allowances_count
 * @property-read \App\Models\Master\Kelas|null $startKelas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserProfiles> $userProfiles
 * @property-read int|null $user_profiles_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats whereMaxKelasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats whereStartKelasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pangkats whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Pangkats extends Model
{
    use HasUlids;

    protected $table = "m_pangkats";

    protected $fillable = [
        'name',
        'golongan',
        'level',
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
