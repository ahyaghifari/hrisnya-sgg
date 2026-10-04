<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NIK/KK/NPWP/BPJS + rekening bank. Salinan dari user_profiles (lihat EmployeeSyncService). */
class EmployeeIdentity extends Model
{
    use HasUlids;

    protected $table = 'employee_identities';

    protected $fillable = [
        'employee_id',
        'no_nik',
        'no_kk',
        'npwp',
        'no_bpjs_kes',
        'no_bpjs_ket',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
