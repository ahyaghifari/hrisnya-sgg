<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran identitas karyawan (KTP/KK/STR/SIP/RKK), salinan user_profile_documents (lihat
 * EmployeeSyncService). `file_path` menunjuk file terenkripsi yang SAMA dengan baris asal -
 * jangan dibuka langsung, pembukaan tetap lewat route lampiran lama (dekripsi + cek izin).
 */
class EmployeeDocument extends Model
{
    use HasUlids;

    protected $table = 'employee_documents';

    protected $fillable = [
        'employee_id',
        'type',
        'file_path',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
