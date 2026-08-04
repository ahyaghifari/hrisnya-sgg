<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koperasi & Bon — input manual per user per periode, tidak ada rumus otomatis.
 */
class PayrollManualLine extends Model
{
    use HasUlids;

    protected $table = 'payroll_manual_lines';

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'component',
        'amount',
        'note',
        'created_by',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
