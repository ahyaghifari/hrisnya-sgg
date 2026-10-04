<?php

namespace App\Models\Employee;

use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Person\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Salinan kondisi karyawan SAAT INI dari users + user_profiles (lihat EmployeeSyncService).
 * Jangan diedit langsung dari aplikasi.
 */
class Employee extends Model
{
    use HasUlids;

    protected $table = 'employees';

    protected $fillable = [
        'person_id',
        'user_id',
        'nip',
        'company_id',
        'employee_type_id',
        'job_position_id',
        'pangkat_id',
        'kelas_id',
        'joining_date',
        'tmt_tetap',
        'status',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'tmt_tetap' => 'date',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes()->withTrashed();
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function careers(): HasMany
    {
        return $this->hasMany(EmployeeCareer::class, 'employee_id')->orderBy('start_date');
    }
}
