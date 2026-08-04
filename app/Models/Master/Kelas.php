<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use App\Models\Payroll\PositionAllowance;
use App\Models\Payroll\TransportAllowance;
use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

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

    /** Karyawan yang kelas_id-nya langsung kelas ini (beda dari lewat Pangkat - lihat
     * pangkatsInRange()). */
    public function userProfiles(): HasMany
    {
        return $this->hasMany(UserProfiles::class, 'kelas_id');
    }

    /** Pangkat itu SENDIRI tidak punya kelas_id - dia punya rentang start_kelas_id..max_kelas_id
     * (lihat Pangkats::startKelas()/maxKelas()). Kelas ini "termasuk" 1 Pangkat kalau level
     * kelas ini berada di antara level start & max Pangkat tsb (inclusive). Bukan relasi FK
     * langsung, makanya query manual (whereHas ke 2 relasi Kelas lain), bukan hasMany biasa. */
    public function pangkatsInRange(): Builder
    {
        return Pangkats::query()
            ->whereHas('startKelas', fn ($q) => $q->where('level', '<=', $this->level))
            ->whereHas('maxKelas', fn ($q) => $q->where('level', '>=', $this->level));
    }
}
