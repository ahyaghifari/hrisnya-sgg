<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alamat karyawan (1:N per `type`). Salinan dari user_profiles (lihat EmployeeSyncService). */
class EmployeeAddress extends Model
{
    use HasUlids;

    protected $table = 'employee_addresses';

    protected $fillable = [
        'employee_id',
        'type',
        'is_primary',
        'country',
        'province',
        'city',
        'district',
        'village',
        'address',
        'postal_code',
        'rt',
        'rw',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
