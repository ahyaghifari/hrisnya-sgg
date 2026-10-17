<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\User;
use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasUlids;

    protected $table = 'payrolls';

    protected $fillable = [
        'user_id',
        'employee_id',
        'payroll_period_id',
        'gross_salary',
        'total_deductions',
        'net_salary',
        'is_paid',
        'slip_sent_at',
        'slip_send_failed_at',
        'slip_send_error',
        'generated_by',
        'generated_at',
        'import_meta',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'import_meta' => 'array',
        'slip_sent_at' => 'datetime',
        'slip_send_failed_at' => 'datetime',
        'gross_salary' => PayrollEncrypted::class,
        'total_deductions' => PayrollEncrypted::class,
        'net_salary' => PayrollEncrypted::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        // withoutGlobalScope('non_karyawan') - Presiden Direktur/Sekretaris Direktur (lihat
        // User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS) tetap punya baris Payroll walau ID-nya masuk
        // NON_KARYAWAN_IDS, tanpa ini relasi user() selalu null buat mereka (nama tampil "-"
        // di semua halaman list/detail payroll) - 2026-08-12.
        return $this->belongsTo(User::class)->withoutGlobalScope('non_karyawan');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function earningItems(): HasMany
    {
        return $this->items()->where('type', PayrollComponentTypeEnum::EARNING->value);
    }

    public function deductionItems(): HasMany
    {
        return $this->items()->where('type', PayrollComponentTypeEnum::DEDUCTION->value);
    }
}
