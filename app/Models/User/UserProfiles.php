<?php

namespace App\Models\User;

use App\Models\Master\OrgStructure;
use App\Models\Master\Religions;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserProfiles
 */
class UserProfiles extends Model
{
    use HasUlids;

    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',
        'nickname',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'no_nik',
        'no_kk',
        'no_bpjs_kes',
        'no_bpjs_ket',
        'bank_account_number',
        'bank_account_name',
        'npwp',
        'country',
        'province',
        'city',
        'district',
        'village',
        'address',
        'postal_code',
        'rt',
        'rw',
        'phone',
        'whatsapp',
        'religion_id',
        'bank_id',
        'org_structure_id',
        'job_position_id',
        'pangkat_id',
        'golongan_id',
        'kelas_id',
        'employee_type_id',
        'profile_picture',
        'joining_date',
    ];

    protected $casts = [
        'profile_picture' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function religion()
    {
        return $this->belongsTo(Religions::class, 'religion_id', 'id');
    }

    public function bank()
    {
        return $this->belongsTo(\App\Models\Master\Banks::class, 'bank_id', 'id');
    }
    
    public function orgStructure()
    {
        return $this->belongsTo(OrgStructure::class, 'org_structure_id');
    }

    public function jobPosition()
    {
        return $this->belongsTo(\App\Models\Master\JobPositions::class, 'job_position_id');
    }

    public function getDepartmentAttribute()
    {
        if (!$this->orgStructure) {
            return null;
        }

        return $this->orgStructure->level === 1
            ? $this->orgStructure
            : ($this->orgStructure->parent?->parent ?? $this->orgStructure->parent);
    }

    public function getDivisionAttribute()
    {
        if (!$this->orgStructure) {
            return null;
        }

        return $this->orgStructure->level === 2
            ? $this->orgStructure
            : $this->orgStructure->parent;
    }

    public function getSubDivisionAttribute()
    {
        if (!$this->orgStructure) {
            return null;
        }

        return $this->orgStructure->level === 3
            ? $this->orgStructure
            : null;
    }

    public function pangkat()
    {
        return $this->belongsTo(\App\Models\Master\Pangkats::class, 'pangkat_id', 'id');
    }

    public function golongan()
    {
        return $this->belongsTo(\App\Models\Master\Golongans::class, 'golongan_id', 'id');
    }

    public function kelas()
    {
        return $this->belongsTo(\App\Models\Master\Kelas::class, 'kelas_id', 'id');
    }

    public function employeeType()
    {
        return $this->belongsTo(\App\Models\Master\EmployeeTypes::class, 'employee_type_id', 'id');
    }
}
