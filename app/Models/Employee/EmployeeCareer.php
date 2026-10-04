<?php

namespace App\Models\Employee;

use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat jabatan karyawan, salinan tabel user_job_positions (lihat EmployeeSyncService). */
class EmployeeCareer extends Model
{
    use HasUlids;

    protected $table = 'employee_careers';

    protected $fillable = [
        'employee_id',
        'user_job_position_id',
        'job_position_id',
        'pangkat_id',
        'kelas_id',
        'assigned_by',
        'start_date',
        'end_date',
        'status',
        'source',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }
}
